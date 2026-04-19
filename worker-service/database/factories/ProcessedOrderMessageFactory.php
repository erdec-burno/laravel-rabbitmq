<?php

namespace Database\Factories;

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
        return [
            'message_id' => (string) fake()->uuid(),
            'message_type' => 'orders.created',
            'order_id' => (string) fake()->uuid(),
            'customer_email' => fake()->safeEmail(),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'currency' => 'USD',
            'occurred_at' => now()->subMinute(),
            'processed_at' => now(),
            'payload' => [
                'id' => (string) fake()->uuid(),
                'type' => 'orders.created',
                'occurred_at' => now()->toIso8601String(),
                'order' => [
                    'order_id' => (string) fake()->uuid(),
                    'customer_email' => fake()->safeEmail(),
                    'amount' => fake()->randomFloat(2, 1, 5000),
                    'currency' => 'USD',
                ],
            ],
        ];
    }
}
