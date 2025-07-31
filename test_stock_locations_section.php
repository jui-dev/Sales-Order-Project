<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\ProductStock;

echo "=== Testing Stock Locations Section ===\n\n";

// Find a product with stock balances
$product = Product::with('stockBalances')->find(1004);
if (!$product) {
    echo "Product 1004 not found!\n";
    exit;
}

echo "Product: {$product->name} (ID: {$product->id})\n";
echo "Available Stock: {$product->available_stocks}\n\n";

echo "=== Stock Locations ===\n";
$stockBalances = $product->stockBalances;

if ($stockBalances->count() > 0) {
    foreach ($stockBalances as $balance) {
        echo "Location Type: {$balance->location_type}\n";
        echo "Location ID: {$balance->location_id}\n";
        echo "Quantity: {$balance->quantity}\n";
        
        // Try to get the location name
        try {
            $locationModel = $balance->location_type::find($balance->location_id);
            $locationName = $locationModel ? $locationModel->name : 'Unknown Location';
            echo "Location Name: {$locationName}\n";
        } catch (Exception $e) {
            echo "Location Name: Error loading location\n";
        }
        
        echo "Status: " . ($balance->quantity > 0 ? 'In Stock' : 'No Stock') . "\n";
        echo "Last Updated: " . ($balance->updated_at ? $balance->updated_at->format('M d, Y H:i') : 'Never') . "\n";
        echo "---\n";
    }
    
    echo "\n=== Summary ===\n";
    echo "Total Locations: {$stockBalances->count()}\n";
    echo "Warehouses: " . $stockBalances->where('location_type', 'App\Models\Warehouse')->count() . "\n";
    echo "Retailers: " . $stockBalances->where('location_type', 'App\Models\Retailer')->count() . "\n";
} else {
    echo "No stock locations found for this product.\n";
}

echo "\n✅ Stock locations section test completed!\n";