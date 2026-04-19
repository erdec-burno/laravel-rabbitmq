<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\ProcessedOrderMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessedOrderMessage>
 */
class ProcessedOrderMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'message_id' => (string) fake()->uuid(),
            'message_type' => 'orders.created',
            'order_id' => $order->id,
            'occurred_at' => now()->subMinute(),
            'processed_at' => now(),
            'payload' => [
                'id' => (string) fake()->uuid(),
                'type' => 'orders.created',
                'occurred_at' => now()->toIso8601String(),
                'order' => [
                    'order_id' => $order->external_order_id,
                    'customer_email' => $order->customer_email,
                    'amount' => (float) $order->amount,
                    'currency' => $order->currency,
                ],
            ],
        ];
    }
}
