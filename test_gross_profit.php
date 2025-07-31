<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Retailer;

echo "Testing Gross Profit Calculation\n";
echo "================================\n\n";

// Create test data
$customer = Customer::factory()->create();
$retailer = Retailer::factory()->create();
$product = Product::factory()->create([
    'purchase_price' => 50.00,
    'selling_price' => 75.00,
    'gross_profit' => null,
]);

echo "Created product: {$product->name}\n";
echo "Purchase price: $50.00\n";
echo "Selling price: $75.00\n";
echo "Initial gross profit: " . ($product->gross_profit ?? 'null') . "\n\n";

// Create an order
$order = Order::create([
    'customer_id' => $customer->id,
    'status' => 'pending',
    'order_date' => now(),
    'total_amount' => 75.00,
    'fulfillment_location_id' => $retailer->id,
    'fulfillment_location_type' => Retailer::class,
]);

OrderItem::create([
    'order_id' => $order->id,
    'product_id' => $product->id,
    'location_id' => $retailer->id,
    'location_type' => Retailer::class,
    'quantity' => 1,
    'unit_price' => 75.00,
    'subtotal' => 75.00,
]);

echo "Created order with status: {$order->status}\n";

// Confirm the order
$order->update(['status' => 'confirmed']);

// Refresh the product
$product->refresh();

echo "After order confirmation:\n";
echo "Order status: {$order->status}\n";
echo "Product gross profit: " . ($product->gross_profit ?? 'null') . "\n";
echo "Product GP attribute: " . ($product->gp ?? 'null') . "\n";

// Expected: Revenue (75.00) - COGS (50.00) = 25.00
$expected = 25.00;
$actual = $product->gross_profit;

echo "\nTest Result: ";
if ($actual == $expected) {
    echo "✅ PASSED - Gross profit calculated correctly\n";
} else {
    echo "❌ FAILED - Expected $expected, got $actual\n";
}

echo "\nTest completed!\n"; 