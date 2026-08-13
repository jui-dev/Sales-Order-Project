<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $purchase = $this->faker->randomFloat(2, 5, 50);
        $selling  = $purchase * $this->faker->randomFloat(2, 1.2, 2);
        return [
            'name' => $this->faker->word(),
            'sku'  => strtoupper($this->faker->bothify('SKU-####')),
            'purchase_price' => $purchase,
            'selling_price'  => $selling,
            // gross_profit is NOT NULL from 2025_07_27_084804_fix_products_pricing_defaults.
            'gross_profit' => round($selling - $purchase, 2),
            'available_stocks' => 100,
        ];
    }
} 