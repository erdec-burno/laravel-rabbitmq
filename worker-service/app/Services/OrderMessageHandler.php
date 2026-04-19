<?php

namespace App\Services;

use App\Models\ProcessedOrderMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrderMessageHandler
{
    /**
     * Handle an incoming order payload.
     *
     * @param array{
     *     id: string,
     *     type: string,
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
        $this->assertValidPayload($payload);

        $processedMessage = ProcessedOrderMessage::query()->firstOrCreate(
            ['message_id' => $payload['id']],
            [
                'message_type' => $payload['type'],
                'order_id' => $payload['order']['order_id'],
                'customer_email' => $payload['order']['customer_email'],
                'amount' => round((float) $payload['order']['amount'], 2),
                'currency' => strtoupper($payload['order']['currency']),
                'occurred_at' => Carbon::parse($payload['occurred_at']),
                'processed_at' => now(),
                'payload' => $payload,
            ],
        );

        if ($processedMessage->wasRecentlyCreated) {
            Log::info('Processed order message', [
                'message_id' => $processedMessage->message_id,
                'event' => $processedMessage->message_type,
                'occurred_at' => $processedMessage->occurred_at?->toIso8601String(),
                'order_id' => $processedMessage->order_id,
                'customer_email' => $processedMessage->customer_email,
                'amount' => (float) $processedMessage->amount,
                'currency' => $processedMessage->currency,
            ]);
        } else {
            Log::info('Skipped duplicate order message', [
                'message_id' => $processedMessage->message_id,
                'order_id' => $processedMessage->order_id,
            ]);
        }

        return $processedMessage;
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
    }
}
