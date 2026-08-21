<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

/**
 * A small, representative catalogue tree: three trades, two shelves each.
 *
 * Deliberately narrow. Seed data exists so a developer can exercise the
 * workflows, not so the catalogue looks full - a wide tree only makes every
 * product picker longer to scroll and every fixture slower to build.
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Electronics' => [
                'description' => 'Electronic devices and accessories',
                'children' => [
                    'Smartphones' => 'Mobile phones and accessories',
                    'Laptops' => 'Portable computers and accessories',
                ],
            ],
            'Clothing' => [
                'description' => 'Apparel and fashion items',
                'children' => [
                    'Men\'s Clothing' => 'Apparel for men',
                    'Women\'s Clothing' => 'Apparel for women',
                ],
            ],
            'Home & Garden' => [
                'description' => 'Home improvement and garden supplies',
                'children' => [
                    'Kitchen & Dining' => 'Kitchen appliances and dining items',
                    'Furniture' => 'Home and office furniture',
                ],
            ],
        ];

        foreach ($tree as $name => $branch) {
            $parent = ProductCategory::updateOrCreate(
                ['name' => $name],
                ['description' => $branch['description'], 'parent_id' => null],
            );

            foreach ($branch['children'] as $childName => $childDescription) {
                ProductCategory::updateOrCreate(
                    ['name' => $childName],
                    ['description' => $childDescription, 'parent_id' => $parent->id],
                );
            }
        }
    }
}
