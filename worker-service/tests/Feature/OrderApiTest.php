<?php

use App\Models\Order;
use App\Models\ProcessedOrderMessage;
use Carbon\CarbonImmutable;

it('returns a paginated list of stored orders', function () {
    $firstOrder = Order::factory()->create([
        'external_order_id' => 'order-001',
        'status' => Order::STATUS_PROCESSED,
    ]);
    $secondOrder = Order::factory()->create([
        'external_order_id' => 'order-002',
        'status' => Order::STATUS_FAILED,
    ]);

    ProcessedOrderMessage::factory()->create([
        'order_id' => $firstOrder->id,
    ]);
    ProcessedOrderMessage::factory()->create([
        'order_id' => $secondOrder->id,
    ]);

    $response = $this->getJson('/api/orders');

    $response->assertOk()
        ->assertJsonFragment([
            'external_order_id' => 'order-001',
            'status' => Order::STATUS_PROCESSED,
        ])
        ->assertJsonFragment([
            'external_order_id' => 'order-002',
            'status' => Order::STATUS_FAILED,
        ]);
});

it('returns a single stored order by external order id', function () {
    $order = Order::factory()->create([
        'external_order_id' => 'order-123',
        'status' => Order::STATUS_PROCESSED,
    ]);

    ProcessedOrderMessage::factory()->create([
        'order_id' => $order->id,
        'message_id' => 'message-123',
        'message_type' => 'orders.created',
    ]);

    $response = $this->getJson('/api/orders/order-123');

    $response->assertOk()->assertJson([
        'data' => [
            'external_order_id' => 'order-123',
            'status' => Order::STATUS_PROCESSED,
            'processed_messages' => [
                [
                    'message_id' => 'message-123',
                    'message_type' => 'orders.created',
                ],
            ],
        ],
    ]);
});

it('filters orders by status', function () {
    Order::factory()->create([
        'external_order_id' => 'order-processed',
        'status' => Order::STATUS_PROCESSED,
    ]);
    Order::factory()->create([
        'external_order_id' => 'order-failed',
        'status' => Order::STATUS_FAILED,
    ]);

    $response = $this->getJson('/api/orders?status='.Order::STATUS_FAILED);

    $response->assertOk()
        ->assertJsonFragment([
            'external_order_id' => 'order-failed',
            'status' => Order::STATUS_FAILED,
        ])
        ->assertJsonMissing([
            'external_order_id' => 'order-processed',
        ]);
});

it('filters orders by partial customer email', function () {
    Order::factory()->create([
        'external_order_id' => 'order-alpha',
        'customer_email' => 'alpha@example.com',
    ]);
    Order::factory()->create([
        'external_order_id' => 'order-bravo',
        'customer_email' => 'bravo@example.com',
    ]);

    $response = $this->getJson('/api/orders?customer_email=alpha');

    $response->assertOk()
        ->assertJsonFragment([
            'external_order_id' => 'order-alpha',
            'customer_email' => 'alpha@example.com',
        ])
        ->assertJsonMissing([
            'external_order_id' => 'order-bravo',
        ]);
});

it('filters orders by received at date range', function () {
    Order::factory()->create([
        'external_order_id' => 'order-early',
        'received_at' => CarbonImmutable::parse('2026-04-10 10:00:00'),
    ]);
    Order::factory()->create([
        'external_order_id' => 'order-middle',
        'received_at' => CarbonImmutable::parse('2026-04-15 10:00:00'),
    ]);
    Order::factory()->create([
        'external_order_id' => 'order-late',
        'received_at' => CarbonImmutable::parse('2026-04-20 10:00:00'),
    ]);

    $response = $this->getJson('/api/orders?date_from=2026-04-12&date_to=2026-04-18');

    $response->assertOk()
        ->assertJsonFragment([
            'external_order_id' => 'order-middle',
        ])
        ->assertJsonMissing([
            'external_order_id' => 'order-early',
        ])
        ->assertJsonMissing([
            'external_order_id' => 'order-late',
        ]);
});

it('validates supported status filter values', function () {
    $response = $this->getJson('/api/orders?status=unknown');

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'status',
    ]);
});

it('validates date range filter values', function () {
    $response = $this->getJson('/api/orders?date_from=2026-04-20&date_to=2026-04-10');

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'date_to',
    ]);
});
