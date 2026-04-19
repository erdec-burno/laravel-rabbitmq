<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_order_id' => (string) fake()->uuid(),
            'customer_email' => fake()->safeEmail(),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'currency' => 'USD',
            'status' => 'processed',
            'received_at' => now()->subMinute(),
        ];
    }
}
