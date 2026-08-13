<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 1000);

        return [
            'invoice_number' => 'INV'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'invoice_date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'payment_status' => 'unpaid',
        ];
    }

    /**
     * A fully paid invoice.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
        ]);
    }
}
