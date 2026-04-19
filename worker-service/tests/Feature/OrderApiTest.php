<?php

use App\Models\Order;
use App\Models\ProcessedOrderMessage;

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
