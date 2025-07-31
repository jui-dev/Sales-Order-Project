<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransfer;
use App\Services\ReturnService;
use App\Services\AccountingService;

echo "Testing Retailer Return Destination Fetch\n";
echo "=======================================\n\n";

// Get a completed stock transfer to a retailer
$stockTransfer = StockTransfer::where('to_location_type', 'App\\Models\\Retailer')
    ->where('status', 'completed')
    ->first();

if (!$stockTransfer) {
    echo "No completed stock transfers to retailers found.\n";
    exit;
}

echo "Testing with stock transfer: " . $stockTransfer->formatted_id . "\n";
echo "From: " . $stockTransfer->fromLocation->name . "\n";
echo "To: " . $stockTransfer->toLocation->name . "\n\n";

// Test the service method
$service = new ReturnService(new AccountingService());

// Test with a product that exists in the transfer
$transferItem = $stockTransfer->items->first();
if (!$transferItem) {
    echo "No items found in this stock transfer.\n";
    exit;
}

$productId = $transferItem->product_id;
echo "Testing with product ID: " . $productId . "\n\n";

$destination = $service->getProductReturnDestination(
    'retailer_return',
    $stockTransfer->id,
    $productId
);

echo "Destination result:\n";
if (isset($destination['return_destination'])) {
    echo "- Success: " . $destination['return_destination']['name'] . "\n";
    echo "- Type: " . $destination['return_destination']['type_name'] . "\n";
    echo "- ID: " . $destination['return_destination']['id'] . "\n";
} else {
    echo "- Error: " . ($destination['error'] ?? 'Unknown error') . "\n";
}

echo "\nTest completed.\n"; 