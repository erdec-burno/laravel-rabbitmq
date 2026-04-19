<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProcessedOrderMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Throwable;

class OrderMessageHandler
{
    /**
     * Handle an incoming order payload.
     *
     * @param array{
     *     id: string,
     *     type: string,
     *     schema_version?: int,
     *     producer?: string,
     *     occurred_at: string,
     *     order: array{
     *         order_id: string,
     *         customer_email: string,
     *         amount: int|float|string,
     *         currency: string
     *     }
     * } $payload
     */
    public function handle(array $payload): ProcessedOrderMessage
    {
        $order = null;

        try {
            $this->assertValidPayload($payload);

            $order = Order::query()->firstOrCreate(
                ['external_order_id' => $payload['order']['order_id']],
                [
                    'customer_email' => $payload['order']['customer_email'],
                    'amount' => round((float) $payload['order']['amount'], 2),
                    'currency' => strtoupper($payload['order']['currency']),
                    'status' => Order::STATUS_RECEIVED,
                    'received_at' => Carbon::parse($payload['occurred_at']),
                ],
            );

            if ($order->status !== Order::STATUS_PROCESSED) {
                $order->forceFill([
                    'status' => Order::STATUS_RECEIVED,
                ])->save();
            }

            $processedMessage = ProcessedOrderMessage::query()->firstOrCreate(
                ['message_id' => $payload['id']],
                [
                    'message_type' => $payload['type'],
                    'order_id' => $order->id,
                    'occurred_at' => Carbon::parse($payload['occurred_at']),
                    'processed_at' => now(),
                    'payload' => $payload,
                ],
            );

            if ($processedMessage->wasRecentlyCreated) {
                $order->forceFill([
                    'status' => Order::STATUS_PROCESSED,
                ])->save();

                Log::info('Processed order message', [
                    'message_id' => $processedMessage->message_id,
                    'event' => $processedMessage->message_type,
                    'schema_version' => $payload['schema_version'] ?? 1,
                    'producer' => $payload['producer'] ?? 'gateway-api',
                    'occurred_at' => $processedMessage->occurred_at?->toIso8601String(),
                    'order_id' => $order->external_order_id,
                    'customer_email' => $order->customer_email,
                    'amount' => (float) $order->amount,
                    'currency' => $order->currency,
                    'status' => $order->status,
                ]);
            } else {
                Log::info('Skipped duplicate order message', [
                    'message_id' => $processedMessage->message_id,
                    'order_id' => $order->external_order_id,
                    'status' => $order->status,
                ]);
            }

            return $processedMessage;
        } catch (Throwable $exception) {
            $order = $this->markOrderAsFailed($payload, $order);

            Log::warning('Failed to process order message', [
                'message_id' => $payload['id'] ?? null,
                'schema_version' => $payload['schema_version'] ?? null,
                'producer' => $payload['producer'] ?? null,
                'order_id' => $order?->external_order_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertValidPayload(array $payload): void
    {
        if (($payload['type'] ?? null) !== 'orders.created') {
            throw new InvalidArgumentException('Unsupported RabbitMQ message type.');
        }

        if (! isset($payload['id'], $payload['occurred_at']) || ! is_array($payload['order'] ?? null)) {
            throw new InvalidArgumentException('Invalid RabbitMQ payload structure.');
        }

        if (
            isset($payload['schema_version'])
            && (! is_int($payload['schema_version']) || $payload['schema_version'] !== 1)
        ) {
            throw new InvalidArgumentException('Unsupported RabbitMQ schema version.');
        }

        if (isset($payload['producer']) && ! is_string($payload['producer'])) {
            throw new InvalidArgumentException('Invalid RabbitMQ producer.');
        }

        $order = $payload['order'];

        if (
            ! isset($order['order_id'], $order['customer_email'], $order['amount'], $order['currency'])
            || ! is_string($order['order_id'])
            || ! is_string($order['customer_email'])
            || ! is_numeric($order['amount'])
            || ! is_string($order['currency'])
        ) {
            throw new InvalidArgumentException('Order payload is missing required fields.');
        }

        if (
            ! Uuid::isValid($payload['id'])
            || ! Uuid::isValid($order['order_id'])
        ) {
            throw new InvalidArgumentException('RabbitMQ payload must contain valid UUID values.');
        }

        if (! filter_var($order['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Order payload must contain a valid customer email.');
        }

        if ((float) $order['amount'] <= 0) {
            throw new InvalidArgumentException('Order payload amount must be greater than zero.');
        }

        if (! preg_match('/^[A-Za-z]{3}$/', $order['currency'])) {
            throw new InvalidArgumentException('Order payload currency must be a 3-letter ISO code.');
        }

        try {
            Carbon::parse($payload['occurred_at']);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Order payload occurred_at must be a valid date.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function markOrderAsFailed(array $payload, ?Order $order): ?Order
    {
        if ($order) {
            $order->forceFill([
                'status' => Order::STATUS_FAILED,
            ])->save();

            return $order;
        }

        $messageType = $payload['type'] ?? null;
        $orderPayload = $payload['order'] ?? null;
        $occurredAt = $payload['occurred_at'] ?? null;

        if (
            ! is_array($orderPayload)
            || ! is_string($messageType)
            || ! is_string($occurredAt)
            || ! isset($orderPayload['order_id'], $orderPayload['customer_email'], $orderPayload['amount'], $orderPayload['currency'])
            || ! is_string($orderPayload['order_id'])
            || ! is_string($orderPayload['customer_email'])
            || ! is_numeric($orderPayload['amount'])
            || ! is_string($orderPayload['currency'])
            || ! Uuid::isValid($orderPayload['order_id'])
        ) {
            return null;
        }

        try {
            $receivedAt = Carbon::parse($occurredAt);
        } catch (Throwable) {
            return null;
        }

        return Order::query()->updateOrCreate(
            ['external_order_id' => $orderPayload['order_id']],
            [
                'customer_email' => $orderPayload['customer_email'],
                'amount' => round((float) $orderPayload['amount'], 2),
                'currency' => strtoupper($orderPayload['currency']),
                'status' => Order::STATUS_FAILED,
                'received_at' => $receivedAt,
            ],
        );
    }
}
