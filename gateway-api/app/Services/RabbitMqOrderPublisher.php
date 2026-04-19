<?php

namespace App\Services;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\Str;
use JsonException;

class RabbitMqOrderPublisher
{
    /**
     * Create a new class instance.
     */
    public function __construct(private QueueManager $queueManager) {}

    /**
     * @param  array{customer_email: string, amount: int|float|string, currency: string}  $orderData
     * @return array{
     *     message_id: string,
     *     queue: string,
     *     type: string,
     *     schema_version: int,
     *     producer: string,
     *     occurred_at: string,
     *     order: array{
     *         order_id: string,
     *         customer_email: string,
     *         amount: float,
     *         currency: string
     *     }
     * }
     *
     * @throws JsonException
     */
    public function publish(array $orderData): array
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'type' => 'orders.created',
            'schema_version' => 1,
            'producer' => 'gateway-api',
            'occurred_at' => now()->toIso8601String(),
            'order' => [
                'order_id' => (string) Str::uuid(),
                'customer_email' => $orderData['customer_email'],
                'amount' => round((float) $orderData['amount'], 2),
                'currency' => Str::upper($orderData['currency']),
            ],
        ];

        $queueName = (string) config('queue.connections.rabbitmq.queue');

        $this->queueManager
            ->connection('rabbitmq')
            ->pushRaw(json_encode($payload, JSON_THROW_ON_ERROR), $queueName);

        return [
            'message_id' => $payload['id'],
            'queue' => $queueName,
            'type' => $payload['type'],
            'schema_version' => $payload['schema_version'],
            'producer' => $payload['producer'],
            'occurred_at' => $payload['occurred_at'],
            'order' => $payload['order'],
        ];
    }
}
