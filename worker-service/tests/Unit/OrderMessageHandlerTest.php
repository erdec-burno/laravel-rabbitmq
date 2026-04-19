<?php

use App\Services\OrderMessageHandler;
use Illuminate\Support\Facades\Log;

it('logs a processed order message', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Processed order message', Mockery::subset([
            'message_id' => 'message-123',
            'event' => 'orders.created',
            'order_id' => 'order-123',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ]));

    app(OrderMessageHandler::class)->handle([
        'id' => 'message-123',
        'type' => 'orders.created',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => 'order-123',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ],
    ]);
});

it('rejects unsupported message types', function () {
    app(OrderMessageHandler::class)->handle([
        'id' => 'message-123',
        'type' => 'orders.cancelled',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => 'order-123',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported RabbitMQ message type.');
