<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories
        $electronics = ProductCategory::create([
            'name' => 'Electronics',
            'description' => 'Electronic devices and accessories',
        ]);

        $clothing = ProductCategory::create([
            'name' => 'Clothing',
            'description' => 'Apparel and fashion items',
        ]);

        $homeGarden = ProductCategory::create([
            'name' => 'Home & Garden',
            'description' => 'Home improvement and garden supplies',
        ]);

        $sports = ProductCategory::create([
            'name' => 'Sports & Outdoors',
            'description' => 'Sports equipment and outdoor gear',
        ]);

        $books = ProductCategory::create([
            'name' => 'Books & Media',
            'description' => 'Books, movies, and media content',
        ]);

        // Create subcategories for Electronics
        ProductCategory::create([
            'name' => 'Smartphones',
            'description' => 'Mobile phones and accessories',
            'parent_id' => $electronics->id,
        ]);

        ProductCategory::create([
            'name' => 'Laptops',
            'description' => 'Portable computers and accessories',
            'parent_id' => $electronics->id,
        ]);

        ProductCategory::create([
            'name' => 'Audio Equipment',
            'description' => 'Speakers, headphones, and audio devices',
            'parent_id' => $electronics->id,
        ]);

        // Create subcategories for Clothing
        ProductCategory::create([
            'name' => 'Men\'s Clothing',
            'description' => 'Apparel for men',
            'parent_id' => $clothing->id,
        ]);

        ProductCategory::create([
            'name' => 'Women\'s Clothing',
            'description' => 'Apparel for women',
            'parent_id' => $clothing->id,
        ]);

        ProductCategory::create([
            'name' => 'Kids\' Clothing',
            'description' => 'Apparel for children',
            'parent_id' => $clothing->id,
        ]);

        // Create subcategories for Home & Garden
        ProductCategory::create([
            'name' => 'Kitchen & Dining',
            'description' => 'Kitchen appliances and dining items',
            'parent_id' => $homeGarden->id,
        ]);

        ProductCategory::create([
            'name' => 'Garden Tools',
            'description' => 'Tools and equipment for gardening',
            'parent_id' => $homeGarden->id,
        ]);

        ProductCategory::create([
            'name' => 'Furniture',
            'description' => 'Home and office furniture',
            'parent_id' => $homeGarden->id,
        ]);

        // Create subcategories for Sports & Outdoors
        ProductCategory::create([
            'name' => 'Fitness Equipment',
            'description' => 'Exercise and fitness equipment',
            'parent_id' => $sports->id,
        ]);

        ProductCategory::create([
            'name' => 'Camping Gear',
            'description' => 'Camping and outdoor equipment',
            'parent_id' => $sports->id,
        ]);

        ProductCategory::create([
            'name' => 'Team Sports',
            'description' => 'Equipment for team sports',
            'parent_id' => $sports->id,
        ]);

        // Create subcategories for Books & Media
        ProductCategory::create([
            'name' => 'Fiction',
            'description' => 'Fictional literature and novels',
            'parent_id' => $books->id,
        ]);

        ProductCategory::create([
            'name' => 'Non-Fiction',
            'description' => 'Educational and reference books',
            'parent_id' => $books->id,
        ]);

        ProductCategory::create([
            'name' => 'Digital Media',
            'description' => 'Digital books, movies, and music',
            'parent_id' => $books->id,
        ]);
    }
}
