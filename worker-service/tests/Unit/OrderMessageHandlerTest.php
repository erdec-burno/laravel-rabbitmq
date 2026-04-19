<?php

use App\Models\Order;
use App\Models\ProcessedOrderMessage;
use App\Services\OrderMessageHandler;
use Illuminate\Support\Facades\Log;

it('stores the order and processed message in the database', function () {
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

    $processedMessage = app(OrderMessageHandler::class)->handle([
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

    expect($processedMessage->message_id)->toBe('message-123');

    $order = Order::query()->first();

    expect($order)->not->toBeNull();
    expect($processedMessage->order_id)->toBe($order->id);

    $this->assertDatabaseHas('orders', [
        'external_order_id' => 'order-123',
        'customer_email' => 'customer@example.com',
        'amount' => 149.99,
        'currency' => 'USD',
        'status' => Order::STATUS_PROCESSED,
    ]);

    $this->assertDatabaseHas('processed_order_messages', [
        'message_id' => 'message-123',
        'message_type' => 'orders.created',
        'order_id' => $order->id,
    ]);
});

it('does not create a duplicate order or message for the same payload', function () {
    Log::shouldReceive('info')->once()->with('Processed order message', Mockery::any());
    Log::shouldReceive('info')
        ->once()
        ->with('Skipped duplicate order message', [
            'message_id' => 'message-123',
            'order_id' => 'order-123',
            'status' => Order::STATUS_PROCESSED,
        ]);

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

    $first = app(OrderMessageHandler::class)->handle($payload);
    $second = app(OrderMessageHandler::class)->handle($payload);

    expect($first->is($second))->toBeTrue();
    expect(Order::query()->count())->toBe(1);
    expect(ProcessedOrderMessage::query()->count())->toBe(1);
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

it('marks the order as failed when processing throws after the order can be identified', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Failed to process order message', Mockery::subset([
            'message_id' => 'message-999',
            'order_id' => 'order-999',
        ]));

    app(OrderMessageHandler::class)->handle([
        'id' => 'message-999',
        'type' => 'orders.cancelled',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => 'order-999',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported RabbitMQ message type.');

it('stores a failed status for a rejected but identifiable order', function () {
    Log::shouldReceive('warning')->once();

    try {
        app(OrderMessageHandler::class)->handle([
            'id' => 'message-999',
            'type' => 'orders.cancelled',
            'occurred_at' => '2026-04-19T18:00:00+00:00',
            'order' => [
                'order_id' => 'order-999',
                'customer_email' => 'customer@example.com',
                'amount' => 149.99,
                'currency' => 'USD',
            ],
        ]);
    } catch (InvalidArgumentException) {
    }

    $this->assertDatabaseHas('orders', [
        'external_order_id' => 'order-999',
        'status' => Order::STATUS_FAILED,
    ]);
});
