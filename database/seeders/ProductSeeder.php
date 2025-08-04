<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get all categories and subcategories
        $categories = ProductCategory::all()->keyBy('name');
        
        // Define products for each category and subcategory
        $products = [
            // Main Categories (IDs 1-5)
            
            // Electronics (ID: 1) - Main Category
            [
                'name' => 'Universal Phone Charger',
                'description' => 'Multi-device wireless charging pad for all smartphones',
                'category_name' => 'Electronics',
                'sku' => 'ELECTRONICS-UNIVERSAL-CHARGER'
            ],
            [
                'name' => 'Bluetooth Speaker',
                'description' => 'Portable waterproof bluetooth speaker with deep bass',
                'category_name' => 'Electronics',
                'sku' => 'ELECTRONICS-BLUETOOTH-SPEAKER'
            ],
            [
                'name' => 'Smart Watch',
                'description' => 'Fitness tracking smartwatch with heart rate monitor',
                'category_name' => 'Electronics',
                'sku' => 'ELECTRONICS-SMART-WATCH'
            ],
            
            // Clothing (ID: 2) - Main Category
            [
                'name' => 'Unisex Hoodie',
                'description' => 'Comfortable cotton hoodie for all ages',
                'category_name' => 'Clothing',
                'sku' => 'CLOTHING-UNISEX-HOODIE'
            ],
            [
                'name' => 'Casual Sneakers',
                'description' => 'Versatile sneakers suitable for daily wear',
                'category_name' => 'Clothing',
                'sku' => 'CLOTHING-CASUAL-SNEAKERS'
            ],
            [
                'name' => 'Fashion Backpack',
                'description' => 'Stylish and functional backpack for everyday use',
                'category_name' => 'Clothing',
                'sku' => 'CLOTHING-FASHION-BACKPACK'
            ],
            
            // Home & Garden (ID: 3) - Main Category
            [
                'name' => 'LED Desk Lamp',
                'description' => 'Adjustable LED desk lamp with touch control',
                'category_name' => 'Home & Garden',
                'sku' => 'HOME-GARDEN-LED-LAMP'
            ],
            [
                'name' => 'Indoor Plant Pot',
                'description' => 'Decorative ceramic pot for indoor plants',
                'category_name' => 'Home & Garden',
                'sku' => 'HOME-GARDEN-PLANT-POT'
            ],
            [
                'name' => 'Wall Clock',
                'description' => 'Modern wall clock with silent movement',
                'category_name' => 'Home & Garden',
                'sku' => 'HOME-GARDEN-WALL-CLOCK'
            ],
            
            // Sports & Outdoors (ID: 4) - Main Category
            [
                'name' => 'Water Bottle',
                'description' => 'Insulated stainless steel water bottle',
                'category_name' => 'Sports & Outdoors',
                'sku' => 'SPORTS-WATER-BOTTLE'
            ],
            [
                'name' => 'Running Shoes',
                'description' => 'Lightweight running shoes with cushioning',
                'category_name' => 'Sports & Outdoors',
                'sku' => 'SPORTS-RUNNING-SHOES'
            ],
            [
                'name' => 'Gym Bag',
                'description' => 'Durable gym bag with multiple compartments',
                'category_name' => 'Sports & Outdoors',
                'sku' => 'SPORTS-GYM-BAG'
            ],
            
            // Books & Media (ID: 5) - Main Category
            [
                'name' => 'Puzzle Book',
                'description' => 'Brain training puzzle book for all ages',
                'category_name' => 'Books & Media',
                'sku' => 'BOOKS-MEDIA-PUZZLE-BOOK'
            ],
            [
                'name' => 'Coloring Book',
                'description' => 'Adult coloring book with intricate designs',
                'category_name' => 'Books & Media',
                'sku' => 'BOOKS-MEDIA-COLORING-BOOK'
            ],
            [
                'name' => 'Journal Notebook',
                'description' => 'Premium leather-bound journal notebook',
                'category_name' => 'Books & Media',
                'sku' => 'BOOKS-MEDIA-JOURNAL'
            ],
            
            // Subcategories (IDs 6-20)
            
            // Electronics - Smartphones (ID: 6)
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with advanced camera system and A17 Pro chip',
                'category_name' => 'Smartphones',
                'sku' => 'IPHONE-15-PRO'
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'Flagship Android smartphone with AI features',
                'category_name' => 'Smartphones',
                'sku' => 'SAMSUNG-S24'
            ],
            [
                'name' => 'Google Pixel 8',
                'description' => 'Pure Android experience with excellent camera',
                'category_name' => 'Smartphones',
                'sku' => 'GOOGLE-PIXEL-8'
            ],
            
            // Electronics - Laptops (ID: 7)
            [
                'name' => 'MacBook Pro 16"',
                'description' => 'Professional laptop with M3 Pro chip',
                'category_name' => 'Laptops',
                'sku' => 'MACBOOK-PRO-16'
            ],
            [
                'name' => 'Dell XPS 15',
                'description' => 'Premium Windows laptop with OLED display',
                'category_name' => 'Laptops',
                'sku' => 'DELL-XPS-15'
            ],
            [
                'name' => 'Lenovo ThinkPad X1',
                'description' => 'Business laptop with excellent keyboard',
                'category_name' => 'Laptops',
                'sku' => 'LENOVO-THINKPAD-X1'
            ],
            
            // Electronics - Audio Equipment (ID: 8)
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Premium noise-cancelling headphones',
                'category_name' => 'Audio Equipment',
                'sku' => 'SONY-WH-1000XM5'
            ],
            [
                'name' => 'AirPods Pro',
                'description' => 'Wireless earbuds with spatial audio',
                'category_name' => 'Audio Equipment',
                'sku' => 'AIRPODS-PRO'
            ],
            [
                'name' => 'Bose QuietComfort 45',
                'description' => 'Comfortable over-ear headphones',
                'category_name' => 'Audio Equipment',
                'sku' => 'BOSE-QC-45'
            ],
            
            // Clothing - Men's Clothing (ID: 9)
            [
                'name' => 'Classic White T-Shirt',
                'description' => 'Premium cotton crew neck t-shirt',
                'category_name' => 'Men\'s Clothing',
                'sku' => 'MEN-TSHIRT-WHITE'
            ],
            [
                'name' => 'Slim Fit Jeans',
                'description' => 'Comfortable stretch denim jeans',
                'category_name' => 'Men\'s Clothing',
                'sku' => 'MEN-JEANS-SLIM'
            ],
            [
                'name' => 'Casual Blazer',
                'description' => 'Versatile blazer for business and casual wear',
                'category_name' => 'Men\'s Clothing',
                'sku' => 'MEN-BLAZER-CASUAL'
            ],
            
            // Clothing - Women's Clothing (ID: 10)
            [
                'name' => 'Floral Summer Dress',
                'description' => 'Lightweight dress perfect for summer',
                'category_name' => 'Women\'s Clothing',
                'sku' => 'WOMEN-DRESS-FLORAL'
            ],
            [
                'name' => 'High-Waist Leggings',
                'description' => 'Comfortable workout and casual leggings',
                'category_name' => 'Women\'s Clothing',
                'sku' => 'WOMEN-LEGGINGS-HIGHWAIST'
            ],
            [
                'name' => 'Silk Blouse',
                'description' => 'Elegant silk blouse for professional wear',
                'category_name' => 'Women\'s Clothing',
                'sku' => 'WOMEN-BLOUSE-SILK'
            ],
            
            // Clothing - Kids' Clothing (ID: 11)
            [
                'name' => 'Kids\' Graphic T-Shirt',
                'description' => 'Fun and colorful t-shirt for children',
                'category_name' => 'Kids\' Clothing',
                'sku' => 'KIDS-TSHIRT-GRAPHIC'
            ],
            [
                'name' => 'Children\'s Denim Jacket',
                'description' => 'Durable denim jacket for kids',
                'category_name' => 'Kids\' Clothing',
                'sku' => 'KIDS-JACKET-DENIM'
            ],
            [
                'name' => 'Kids\' Comfortable Pants',
                'description' => 'Soft and stretchy pants for active children',
                'category_name' => 'Kids\' Clothing',
                'sku' => 'KIDS-PANTS-COMFORT'
            ],
            
            // Home & Garden - Kitchen & Dining (ID: 12)
            [
                'name' => 'Stainless Steel Cookware Set',
                'description' => 'Professional-grade cookware set',
                'category_name' => 'Kitchen & Dining',
                'sku' => 'KITCHEN-COOKWARE-SET'
            ],
            [
                'name' => 'Coffee Maker',
                'description' => 'Programmable coffee maker with thermal carafe',
                'category_name' => 'Kitchen & Dining',
                'sku' => 'KITCHEN-COFFEE-MAKER'
            ],
            [
                'name' => 'Kitchen Knife Set',
                'description' => 'Sharp and durable kitchen knives',
                'category_name' => 'Kitchen & Dining',
                'sku' => 'KITCHEN-KNIFE-SET'
            ],
            
            // Home & Garden - Garden Tools (ID: 13)
            [
                'name' => 'Garden Shovel',
                'description' => 'Heavy-duty garden shovel for landscaping',
                'category_name' => 'Garden Tools',
                'sku' => 'GARDEN-SHOVEL'
            ],
            [
                'name' => 'Pruning Shears',
                'description' => 'Professional pruning shears for plants',
                'category_name' => 'Garden Tools',
                'sku' => 'GARDEN-PRUNING-SHEARS'
            ],
            [
                'name' => 'Garden Hose',
                'description' => 'Durable garden hose with spray nozzle',
                'category_name' => 'Garden Tools',
                'sku' => 'GARDEN-HOSE'
            ],
            
            // Home & Garden - Furniture (ID: 14)
            [
                'name' => 'Modern Sofa',
                'description' => 'Comfortable 3-seater modern sofa',
                'category_name' => 'Furniture',
                'sku' => 'FURNITURE-SOFA-MODERN'
            ],
            [
                'name' => 'Dining Table Set',
                'description' => '6-seater dining table with chairs',
                'category_name' => 'Furniture',
                'sku' => 'FURNITURE-DINING-SET'
            ],
            [
                'name' => 'Office Desk',
                'description' => 'Ergonomic office desk with storage',
                'category_name' => 'Furniture',
                'sku' => 'FURNITURE-OFFICE-DESK'
            ],
            
            // Sports & Outdoors - Fitness Equipment (ID: 15)
            [
                'name' => 'Treadmill',
                'description' => 'Motorized treadmill with incline',
                'category_name' => 'Fitness Equipment',
                'sku' => 'SPORTS-TREADMILL'
            ],
            [
                'name' => 'Dumbbell Set',
                'description' => 'Adjustable dumbbell set for home gym',
                'category_name' => 'Fitness Equipment',
                'sku' => 'SPORTS-DUMBBELL-SET'
            ],
            [
                'name' => 'Yoga Mat',
                'description' => 'Non-slip yoga mat for workouts',
                'category_name' => 'Fitness Equipment',
                'sku' => 'SPORTS-YOGA-MAT'
            ],
            
            // Sports & Outdoors - Camping Gear (ID: 16)
            [
                'name' => 'Tent 4-Person',
                'description' => 'Weather-resistant 4-person tent',
                'category_name' => 'Camping Gear',
                'sku' => 'SPORTS-TENT-4P'
            ],
            [
                'name' => 'Sleeping Bag',
                'description' => 'Warm sleeping bag for cold weather',
                'category_name' => 'Camping Gear',
                'sku' => 'SPORTS-SLEEPING-BAG'
            ],
            [
                'name' => 'Camping Stove',
                'description' => 'Portable camping stove with fuel',
                'category_name' => 'Camping Gear',
                'sku' => 'SPORTS-CAMPING-STOVE'
            ],
            
            // Sports & Outdoors - Team Sports (ID: 17)
            [
                'name' => 'Soccer Ball',
                'description' => 'Professional size 5 soccer ball',
                'category_name' => 'Team Sports',
                'sku' => 'SPORTS-SOCCER-BALL'
            ],
            [
                'name' => 'Basketball',
                'description' => 'Official size basketball',
                'category_name' => 'Team Sports',
                'sku' => 'SPORTS-BASKETBALL'
            ],
            [
                'name' => 'Baseball Glove',
                'description' => 'Leather baseball glove for fielding',
                'category_name' => 'Team Sports',
                'sku' => 'SPORTS-BASEBALL-GLOVE'
            ],
            
            // Books & Media - Fiction (ID: 18)
            [
                'name' => 'The Great Gatsby',
                'description' => 'Classic American novel by F. Scott Fitzgerald',
                'category_name' => 'Fiction',
                'sku' => 'BOOKS-FICTION-GATSBY'
            ],
            [
                'name' => '1984',
                'description' => 'Dystopian novel by George Orwell',
                'category_name' => 'Fiction',
                'sku' => 'BOOKS-FICTION-1984'
            ],
            [
                'name' => 'Pride and Prejudice',
                'description' => 'Romance novel by Jane Austen',
                'category_name' => 'Fiction',
                'sku' => 'BOOKS-FICTION-PRIDE'
            ],
            
            // Books & Media - Non-Fiction (ID: 19)
            [
                'name' => 'The Art of War',
                'description' => 'Ancient Chinese military treatise',
                'category_name' => 'Non-Fiction',
                'sku' => 'BOOKS-NONFICTION-ART-OF-WAR'
            ],
            [
                'name' => 'Sapiens: A Brief History',
                'description' => 'History of humankind by Yuval Noah Harari',
                'category_name' => 'Non-Fiction',
                'sku' => 'BOOKS-NONFICTION-SAPIENS'
            ],
            [
                'name' => 'The Psychology of Money',
                'description' => 'Financial psychology book by Morgan Housel',
                'category_name' => 'Non-Fiction',
                'sku' => 'BOOKS-NONFICTION-PSYCHOLOGY-MONEY'
            ],
            
            // Books & Media - Digital Media (ID: 20)
            [
                'name' => 'Digital Photography Course',
                'description' => 'Online course for photography beginners',
                'category_name' => 'Digital Media',
                'sku' => 'MEDIA-COURSE-PHOTOGRAPHY'
            ],
            [
                'name' => 'Programming Fundamentals',
                'description' => 'Digital course on programming basics',
                'category_name' => 'Digital Media',
                'sku' => 'MEDIA-COURSE-PROGRAMMING'
            ],
            [
                'name' => 'Digital Art Masterclass',
                'description' => 'Online masterclass for digital artists',
                'category_name' => 'Digital Media',
                'sku' => 'MEDIA-COURSE-DIGITAL-ART'
            ],
        ];

        foreach ($products as $productData) {
            // Find the category by name
            $category = $categories->get($productData['category_name']);
            
            if ($category) {
                Product::updateOrCreate(
                    ['sku' => $productData['sku']],
                    [
                        'name' => $productData['name'],
                        'sku' => $productData['sku'],
                        'description' => $productData['description'],
                        'category_id' => $category->id,
                        'markup' => 25.00, // 25% markup as requested
                        'auto_pricing_enabled' => 1, // Enable auto pricing
                        'purchase_price' => 0.00, // Will be set by system functionality
                        'selling_price' => 0.00, // Will be calculated by auto pricing
                        'gross_profit' => 0.00, // Will be calculated by system
                        'available_stocks' => 0, // Will be managed by stock transactions
                    ]
                );
            }
        }
    }
} 