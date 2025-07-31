<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;
use App\Models\Product;

echo "=== Fixing Existing Return Transactions ===\n\n";

// Get all return transactions that need to be processed
$returnTransactions = StockTransaction::whereIn('transaction_type', [
    'customer_return',
    'vendor_return',
    'retailer_return'
])
->whereIn('status', ['pending', 'approved', 'completed'])
->get();

echo "Found {$returnTransactions->count()} return transactions to process\n\n";

$processed = 0;
$errors = 0;

foreach ($returnTransactions as $transaction) {
    try {
        echo "Processing transaction ID: {$transaction->id} - Type: {$transaction->transaction_type} - Quantity: {$transaction->quantity}\n";
        
        // Call the updateProductStock method to update product_stocks table
        $transaction->updateProductStock();
        
        $processed++;
        echo "  ✅ Processed successfully\n";
    } catch (Exception $e) {
        $errors++;
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Processed: {$processed}\n";
echo "Errors: {$errors}\n";

// Now recalculate all product stocks
echo "\n=== Recalculating Product Stocks ===\n";

$products = Product::all();
$updated = 0;

foreach ($products as $product) {
    try {
        $oldStock = $product->available_stocks;
        
        // Recalculate based on product_stocks table
        $newStock = (int) $product->stockBalances()->sum('quantity');
        $newStock = max(0, $newStock);
        
        $product->update(['available_stocks' => $newStock]);
        
        if ($oldStock != $newStock) {
            echo "Product {$product->id} ({$product->name}): {$oldStock} → {$newStock}\n";
        }
        
        $updated++;
    } catch (Exception $e) {
        echo "Error updating product {$product->id}: " . $e->getMessage() . "\n";
    }
}

echo "\nUpdated {$updated} products\n";
echo "✅ Fix completed!\n";