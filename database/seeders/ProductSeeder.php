<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;

/**
 * A dozen products, spread across the seeded categories.
 *
 * Enough to fill a picker and raise a realistic order, few enough to read at a
 * glance. Prices are deliberately left at zero: products carry no selling
 * price of their own, cost is agreed per vendor on that vendor's price list,
 * and selling price is derived when a goods receipt is posted.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ProductCategory::all()->keyBy('name');

        $products = [
            // Main categories
            [
                'sku' => 'ELECTRONICS-BLUETOOTH-SPEAKER',
                'name' => 'Bluetooth Speaker',
                'description' => 'Portable waterproof bluetooth speaker with deep bass',
                'category_name' => 'Electronics',
            ],
            [
                'sku' => 'CLOTHING-UNISEX-HOODIE',
                'name' => 'Unisex Hoodie',
                'description' => 'Comfortable cotton hoodie for all ages',
                'category_name' => 'Clothing',
            ],
            [
                'sku' => 'HOME-GARDEN-LED-LAMP',
                'name' => 'LED Desk Lamp',
                'description' => 'Adjustable LED desk lamp with touch control',
                'category_name' => 'Home & Garden',
            ],

            // Electronics - Smartphones
            [
                'sku' => 'IPHONE-15-PRO',
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with advanced camera system and A17 Pro chip',
                'category_name' => 'Smartphones',
            ],
            [
                'sku' => 'SAMSUNG-S24',
                'name' => 'Samsung Galaxy S24',
                'description' => 'Flagship Android smartphone with AI features',
                'category_name' => 'Smartphones',
            ],

            // Electronics - Laptops
            [
                'sku' => 'MACBOOK-PRO-16',
                'name' => 'MacBook Pro 16"',
                'description' => 'Professional laptop with M3 Pro chip',
                'category_name' => 'Laptops',
            ],
            [
                'sku' => 'DELL-XPS-15',
                'name' => 'Dell XPS 15',
                'description' => 'Premium Windows laptop with OLED display',
                'category_name' => 'Laptops',
            ],

            // Clothing - Men's Clothing
            [
                'sku' => 'MEN-TSHIRT-WHITE',
                'name' => 'Classic White T-Shirt',
                'description' => 'Premium cotton crew neck t-shirt',
                'category_name' => 'Men\'s Clothing',
            ],
            [
                'sku' => 'MEN-JEANS-SLIM',
                'name' => 'Slim Fit Jeans',
                'description' => 'Comfortable stretch denim jeans',
                'category_name' => 'Men\'s Clothing',
            ],

            // Clothing - Women's Clothing
            [
                'sku' => 'WOMEN-DRESS-FLORAL',
                'name' => 'Floral Summer Dress',
                'description' => 'Lightweight dress perfect for summer',
                'category_name' => 'Women\'s Clothing',
            ],

            // Home & Garden - Kitchen & Dining
            [
                'sku' => 'KITCHEN-COFFEE-MAKER',
                'name' => 'Coffee Maker',
                'description' => 'Programmable coffee maker with thermal carafe',
                'category_name' => 'Kitchen & Dining',
            ],

            // Home & Garden - Furniture
            [
                'sku' => 'FURNITURE-OFFICE-DESK',
                'name' => 'Office Desk',
                'description' => 'Ergonomic office desk with storage',
                'category_name' => 'Furniture',
            ],
        ];

        foreach ($products as $productData) {
            $category = $categories->get($productData['category_name']);

            if (! $category) {
                continue;
            }

            Product::updateOrCreate(
                ['sku' => $productData['sku']],
                [
                    'name' => $productData['name'],
                    'sku' => $productData['sku'],
                    'description' => $productData['description'],
                    'category_id' => $category->id,
                    'markup' => 25.00,
                    'auto_pricing_enabled' => 1,
                    'purchase_price' => 0.00,
                    'selling_price' => 0.00,
                    'gross_profit' => 0.00,
                    'available_stocks' => 0,
                ]
            );
        }
    }
}
