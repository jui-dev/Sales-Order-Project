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
            'available_stocks' => 100,
        ];
    }
} 