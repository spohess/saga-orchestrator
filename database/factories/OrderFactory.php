<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'product' => fake()->word(),
            'quantity' => fake()->numberBetween(1, 10),
            'total_price' => fake()->numberBetween(1000, 100000),
            'status' => 'pending',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'external_subscription_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
