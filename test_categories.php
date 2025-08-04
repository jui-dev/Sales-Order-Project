<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing product categories...\n";
    
    $count = \App\Models\ProductCategory::count();
    echo "Found " . $count . " categories in database\n";
    
    if ($count > 0) {
        $mainCategories = \App\Models\ProductCategory::getMainCategories();
        echo "Found " . $mainCategories->count() . " main categories\n";
        
        foreach ($mainCategories as $category) {
            echo "- " . $category->name . " (ID: " . $category->id . ")\n";
            echo "  Subcategories: " . $category->subcategories->count() . "\n";
        }
    } else {
        echo "No categories found. Running seeder...\n";
        \Artisan::call('db:seed', ['--class' => 'ProductCategorySeeder']);
        echo "Seeder completed\n";
        
        $count = \App\Models\ProductCategory::count();
        echo "Now found " . $count . " categories\n";
    }
    
    echo "\nTest completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 