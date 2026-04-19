<?php

use App\Models\ProcessedOrderMessage;
use App\Services\OrderMessageHandler;
use Illuminate\Support\Facades\Log;

it('stores a processed order message in the database', function () {
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

    $this->assertDatabaseHas('processed_order_messages', [
        'message_id' => 'message-123',
        'message_type' => 'orders.created',
        'order_id' => 'order-123',
        'customer_email' => 'customer@example.com',
        'amount' => 149.99,
        'currency' => 'USD',
    ]);
});

it('does not create a duplicate record for the same message', function () {
    Log::shouldReceive('info')->once()->with('Processed order message', Mockery::any());
    Log::shouldReceive('info')
        ->once()
        ->with('Skipped duplicate order message', [
            'message_id' => 'message-123',
            'order_id' => 'order-123',
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
