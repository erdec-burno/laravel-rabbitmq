<?php

use App\Queue\Jobs\IncomingOrderRabbitMQJob;
use App\Services\OrderMessageHandler;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

it('delegates incoming orders to the message handler and acknowledges the job', function () {
    $payload = [
        'id' => 'message-123',
        'type' => 'orders.created',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => 'order-123',
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
