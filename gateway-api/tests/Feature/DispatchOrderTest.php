<?php

use App\Services\RabbitMqOrderPublisher;

it('accepts an order and returns the dispatch metadata', function () {
    $dispatch = [
        'message_id' => 'message-123',
        'queue' => 'orders',
        'type' => 'orders.created',
        'schema_version' => 1,
        'producer' => 'gateway-api',
        'occurred_at' => '2026-04-19T18:00:00+00:00',
        'order' => [
            'order_id' => 'order-123',
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'USD',
        ],
    ];

    $publisher = Mockery::mock(RabbitMqOrderPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->with([
            'customer_email' => 'customer@example.com',
            'amount' => 149.99,
            'currency' => 'usd',
        ])
        ->andReturn($dispatch);

    app()->instance(RabbitMqOrderPublisher::class, $publisher);

    $response = $this->postJson('/api/orders', [
        'customer_email' => 'customer@example.com',
        'amount' => 149.99,
        'currency' => 'usd',
    ]);

    $response->assertAccepted()->assertJson([
        'data' => [
            'status' => 'accepted',
            'message_id' => 'message-123',
            'queue' => 'orders',
            'event' => 'orders.created',
            'schema_version' => 1,
            'producer' => 'gateway-api',
            'occurred_at' => '2026-04-19T18:00:00+00:00',
            'order' => [
                'order_id' => 'order-123',
                'customer_email' => 'customer@example.com',
                'amount' => 149.99,
                'currency' => 'USD',
            ],
        ],
    ]);
});

it('validates the order payload before publishing', function () {
    $response = $this->postJson('/api/orders', [
        'customer_email' => 'invalid-email',
        'amount' => 0,
        'currency' => 'us',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'customer_email',
        'amount',
        'currency',
    ]);
});
