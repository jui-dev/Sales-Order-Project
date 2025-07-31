<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\ProductStock;

echo "=== Testing Stock Fix ===\n\n";

$product = Product::find(1004);
if (!$product) {
    echo "Product 1004 not found!\n";
    exit;
}

echo "Product: {$product->name} (ID: {$product->id})\n";
echo "Current Available Stock: {$product->available_stocks}\n\n";

echo "=== Stock Balances ===\n";
$stockBalances = $product->stockBalances;
foreach ($stockBalances as $balance) {
    echo "Location: {$balance->location_type} (ID: {$balance->location_id}) - Quantity: {$balance->quantity}\n";
}

echo "\n=== Return Transactions ===\n";
$returnTransactions = $product->stockTransactions()
    ->whereIn('transaction_type', ['customer_return', 'vendor_return'])
    ->whereIn('status', ['pending', 'approved', 'completed'])
    ->get();

foreach ($returnTransactions as $txn) {
    echo "Type: {$txn->transaction_type}, Direction: {$txn->direction}, Quantity: {$txn->quantity}, Status: {$txn->status}\n";
}

echo "\n=== Manual Calculation ===\n";
$totalStockBalances = $stockBalances->sum('quantity');
echo "Total from product_stocks: {$totalStockBalances}\n";

$customerReturns = $returnTransactions->where('transaction_type', 'customer_return')->sum('quantity');
$vendorReturns = $returnTransactions->where('transaction_type', 'vendor_return')->sum('quantity');

echo "Customer Returns: {$customerReturns}\n";
echo "Vendor Returns: {$vendorReturns}\n";

// Customer returns should increase stock, vendor returns should decrease stock
$expectedStock = $totalStockBalances + $customerReturns - $vendorReturns;
echo "Expected Available Stock: {$expectedStock}\n";
echo "Current Available Stock: {$product->available_stocks}\n";
echo "Difference: " . ($expectedStock - $product->available_stocks) . "\n";

if ($expectedStock == $product->available_stocks) {
    echo "\n✅ Stock calculation is correct!\n";
} else {
    echo "\n❌ Stock calculation is incorrect!\n";
}