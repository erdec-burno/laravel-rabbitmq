<?php

use App\Queue\Jobs\IncomingOrderRabbitMQJob;
use App\Services\OrderMessageHandler;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

it('delegates incoming orders to the message handler and acknowledges the job', function () {
    $payload = [
        'id' => '11111111-1111-1111-1111-111111111111',
        'type' => 'orders.created',
        'schema_version' => 1,
        'producer' => 'gateway-api',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => '11111111-1111-1111-1111-111111111123',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ],
    ];

    $handler = Mockery::mock(OrderMessageHandler::class);
    $handler->shouldReceive('handle')->once()->with($payload);
    app()->instance(OrderMessageHandler::class, $handler);

    $queue = Mockery::mock(RabbitMQQueue::class);
    $queue->shouldReceive('ack')->once();

    $job = new IncomingOrderRabbitMQJob(
        app(),
        $queue,
        new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR)),
        'rabbitmq',
        'orders',
    );

    $job->fire();
});

it('rejects malformed messages without retrying them', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Failed to process order message', Mockery::subset([
            'message_id' => '11111111-1111-1111-1111-111111111333',
            'producer' => 'gateway-api',
        ]));

    Log::shouldReceive('warning')
        ->once()
        ->with('Rejected RabbitMQ message without retry', Mockery::subset([
            'queue' => 'orders',
            'job' => 'incoming-order',
        ]));

    $queue = Mockery::mock(RabbitMQQueue::class);
    $queue->shouldReceive('reject')->once();

    $job = new IncomingOrderRabbitMQJob(
        app(),
        $queue,
        new AMQPMessage(json_encode([
            'id' => '11111111-1111-1111-1111-111111111333',
            'type' => 'orders.created',
            'schema_version' => 1,
            'producer' => 'gateway-api',
            'occurred_at' => '2026-04-19T18:00:00+00:00',
            'order' => [
                'order_id' => 'not-a-uuid',
                'customer_email' => 'bad-email',
                'amount' => 0,
                'currency' => 'USDT',
            ],
        ], JSON_THROW_ON_ERROR)),
        'rabbitmq',
        'orders',
    );

    $job->fire();
});
