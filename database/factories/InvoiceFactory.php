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
     * Keep the figures on an invoice consistent with each other.
     *
     * total is subtotal plus tax less discount, always. Tests routinely
     * override the total alone and leave the generated subtotal behind, which
     * produces an invoice that cannot be posted - the receivable would be
     * taken from one figure and the revenue from another, and the entry would
     * still balance, so nothing downstream would ever notice. Trusting the
     * stated total and deriving the subtotal from it keeps that data valid
     * without every caller having to spell out all four numbers.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Invoice $invoice) {
            $expected = round((float) $invoice->subtotal + (float) $invoice->tax - (float) $invoice->discount, 2);

            if (abs($expected - (float) $invoice->total) >= 0.01) {
                $invoice->subtotal = round((float) $invoice->total - (float) $invoice->tax + (float) $invoice->discount, 2);
            }
        });
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
