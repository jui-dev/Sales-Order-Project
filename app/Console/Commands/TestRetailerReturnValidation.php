<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockTransfer;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;

class TestRetailerReturnValidation extends Command
{
    protected $signature = 'test:retailer-return-validation';
    protected $description = 'Test retailer return validation for completed stock transfers only';

    public function handle()
    {
        $this->info('Testing Retailer Return Validation...');
        
        // Get test data
        $retailer = Retailer::first();
        $warehouse = Warehouse::first();
        $product = Product::first();
        
        if (!$retailer || !$warehouse || !$product) {
            $this->error('Required test data not found. Please ensure you have retailers, warehouses, and products in the database.');
            return 1;
        }
        
        $this->info("Using Retailer: {$retailer->name}");
        $this->info("Using Warehouse: {$warehouse->name}");
        $this->info("Using Product: {$product->name}");
        
        // Test 1: Check if only completed stock transfers are returned
        $this->info("\n1. Testing getAvailableStockTransfers method...");
        
        $returnService = app(ReturnService::class);
        $availableTransfers = $returnService->getAvailableStockTransfers($retailer);
        
        $this->info("Found " . $availableTransfers->count() . " available stock transfers");
        
        foreach ($availableTransfers as $transfer) {
            $this->info("- Transfer: {$transfer['transfer_number']}, Status: {$transfer['status']}");
            
            if ($transfer['status'] !== 'completed') {
                $this->error("ERROR: Non-completed stock transfer found in available transfers!");
                return 1;
            }
        }
        
        $this->info("✓ All available stock transfers are completed");
        
        // Test 2: Create a pending stock transfer and verify it's not available
        $this->info("\n2. Testing with pending stock transfer...");
        
        $pendingTransfer = StockTransfer::create([
            'from_location_id' => $warehouse->id,
            'to_location_id' => $retailer->id,
            'from_location_type' => Warehouse::class,
            'to_location_type' => Retailer::class,
            'status' => 'pending',
            'transfer_date' => now(),
            'notes' => 'Test pending transfer'
        ]);
        
        // Add item to the transfer
        DB::table('stock_transfer_items')->insert([
            'stock_transfer_id' => $pendingTransfer->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->info("Created pending stock transfer: {$pendingTransfer->formatted_id}");
        
        // Check if it's available
        $availableTransfersAfter = $returnService->getAvailableStockTransfers($retailer);
        $pendingTransferInList = $availableTransfersAfter->where('id', $pendingTransfer->id)->first();
        
        if ($pendingTransferInList) {
            $this->error("ERROR: Pending stock transfer is available for returns!");
            return 1;
        }
        
        $this->info("✓ Pending stock transfer correctly excluded from available transfers");
        
        // Test 3: Test validation in validateReturnQuantity
        $this->info("\n3. Testing validateReturnQuantity method...");
        
        $validationResult = $returnService->validateReturnQuantity(
            'retailer_return',
            $pendingTransfer->id,
            $product->id,
            5
        );
        
        if ($validationResult['valid']) {
            $this->error("ERROR: Validation should fail for pending stock transfer!");
            return 1;
        }
        
        $this->info("✓ Validation correctly fails for pending stock transfer");
        $this->info("Error message: " . implode(', ', $validationResult['errors']));
        
        // Test 4: Test getProductReturnDestination
        $this->info("\n4. Testing getProductReturnDestination method...");
        
        $destinationResult = $returnService->getProductReturnDestination(
            'retailer_return',
            $pendingTransfer->id,
            $product->id
        );
        
        if (!isset($destinationResult['error'])) {
            $this->error("ERROR: getProductReturnDestination should return error for pending stock transfer!");
            return 1;
        }
        
        $this->info("✓ getProductReturnDestination correctly returns error for pending stock transfer");
        $this->info("Error message: " . $destinationResult['error']);
        
        // Clean up test data
        $this->info("\n5. Cleaning up test data...");
        DB::table('stock_transfer_items')->where('stock_transfer_id', $pendingTransfer->id)->delete();
        $pendingTransfer->delete();
        
        $this->info("✓ Test data cleaned up");
        
        $this->info("\n🎉 All tests passed! Retailer return validation is working correctly.");
        $this->info("✓ Only completed stock transfers are available for returns");
        $this->info("✓ Pending stock transfers are correctly excluded");
        $this->info("✓ Validation methods properly check stock transfer status");
        
        return 0;
    }
} 