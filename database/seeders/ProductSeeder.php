<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['id' => 1001, 'name' => 'Laptop Pro 15',       'description' => 'High-performance laptop'],
            ['id' => 1002, 'name' => 'Smartphone X',        'description' => 'Flagship smartphone'],
            ['id' => 1003, 'name' => 'Wireless Earbuds',    'description' => 'Noise-cancelling earbuds'],
            ['id' => 1004, 'name' => '4K Monitor 27"',     'description' => 'Ultra-sharp 4K display'],
            ['id' => 1005, 'name' => 'Mechanical Keyboard', 'description' => 'RGB back-lit keyboard'],
        ];

        foreach ($products as $data) {
            $sku = Str::upper(Str::slug($data['name']));

            Product::updateOrCreate(
                ['id' => $data['id']],
                [
                    'name'                 => $data['name'],
                    'sku'                  => $sku,
                    'description'          => $data['description'],
                    'purchase_price'       => 0,
                    'profit_margin'        => 25,
                    'auto_pricing_enabled' => true,
                ]
            );
        }
    }
} 