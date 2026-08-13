<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 20);
        $unitPrice = $this->faker->randomFloat(2, 10, 200);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'description' => $this->faker->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }
}
