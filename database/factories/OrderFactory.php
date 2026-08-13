<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'total_amount' => 0,
        ];
    }

    /**
     * A confirmed order — stock reserved and a picking list raised.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }
}
