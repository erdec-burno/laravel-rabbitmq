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

it('returns a paginated list of processed order messages', function () {
    $order = Order::factory()->create([
        'external_order_id' => 'order-processed-list',
        'status' => Order::STATUS_PROCESSED,
    ]);

    ProcessedOrderMessage::factory()->create([
        'order_id' => $order->id,
        'message_id' => 'message-list-123',
        'message_type' => 'orders.created',
    ]);

    $response = $this->getJson('/api/processed-order-messages');

    $response->assertOk()->assertJsonFragment([
        'message_id' => 'message-list-123',
        'message_type' => 'orders.created',
        'external_order_id' => 'order-processed-list',
    ]);
});

it('returns a single processed order message by message id', function () {
    $order = Order::factory()->create([
        'external_order_id' => 'order-message-show',
        'status' => Order::STATUS_PROCESSED,
    ]);

    ProcessedOrderMessage::factory()->create([
        'order_id' => $order->id,
        'message_id' => 'message-show-123',
        'message_type' => 'orders.created',
    ]);

    $response = $this->getJson('/api/processed-order-messages/message-show-123');

    $response->assertOk()->assertJson([
        'data' => [
            'message_id' => 'message-show-123',
            'message_type' => 'orders.created',
            'order' => [
                'external_order_id' => 'order-message-show',
                'status' => Order::STATUS_PROCESSED,
            ],
        ],
    ]);
});

it('filters processed order messages by message type', function () {
    ProcessedOrderMessage::factory()->create([
        'message_id' => 'message-created-123',
        'message_type' => 'orders.created',
    ]);
    ProcessedOrderMessage::factory()->create([
        'message_id' => 'message-failed-123',
        'message_type' => 'orders.failed',
    ]);

    $response = $this->getJson('/api/processed-order-messages?message_type=orders.failed');

    $response->assertOk()
        ->assertJsonFragment([
            'message_id' => 'message-failed-123',
            'message_type' => 'orders.failed',
        ])
        ->assertJsonMissing([
            'message_id' => 'message-created-123',
        ]);
});

it('filters processed order messages by partial message id', function () {
    ProcessedOrderMessage::factory()->create([
        'message_id' => 'message-alpha-123',
    ]);
    ProcessedOrderMessage::factory()->create([
        'message_id' => 'message-bravo-456',
    ]);

    $response = $this->getJson('/api/processed-order-messages?message_id=alpha');

    $response->assertOk()
        ->assertJsonFragment([
            'message_id' => 'message-alpha-123',
        ])
        ->assertJsonMissing([
            'message_id' => 'message-bravo-456',
        ]);
});

it('filters processed order messages by partial external order id', function () {
    $alphaOrder = Order::factory()->create([
        'external_order_id' => 'order-alpha-123',
    ]);
    $bravoOrder = Order::factory()->create([
        'external_order_id' => 'order-bravo-456',
    ]);

    ProcessedOrderMessage::factory()->create([
        'order_id' => $alphaOrder->id,
        'message_id' => 'message-order-alpha',
    ]);
    ProcessedOrderMessage::factory()->create([
        'order_id' => $bravoOrder->id,
        'message_id' => 'message-order-bravo',
    ]);

    $response = $this->getJson('/api/processed-order-messages?external_order_id=alpha');

    $response->assertOk()
        ->assertJsonFragment([
            'message_id' => 'message-order-alpha',
            'external_order_id' => 'order-alpha-123',
        ])
        ->assertJsonMissing([
            'message_id' => 'message-order-bravo',
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
