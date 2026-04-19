<?php

namespace App\Services;

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
    public function handle(array $payload): void
    {
        $this->assertValidPayload($payload);

        Log::info('Processed order message', [
            'message_id' => $payload['id'],
            'event' => $payload['type'],
            'occurred_at' => $payload['occurred_at'],
            'order_id' => $payload['order']['order_id'],
            'customer_email' => $payload['order']['customer_email'],
            'amount' => (float) $payload['order']['amount'],
            'currency' => $payload['order']['currency'],
        ]);
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
