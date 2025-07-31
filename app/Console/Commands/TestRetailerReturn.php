<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockTransaction;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockTransfer;

class TestRetailerReturn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:retailer-return';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test retailer return functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Retailer Return Functionality');
        $this->info('=====================================');
        $this->newLine();

        try {
            // Check if we have retailers
            $retailers = Retailer::all();
            $this->info("Found {$retailers->count()} retailers");
            
            if ($retailers->count() > 0) {
                $retailer = $retailers->first();
                $this->info("Using retailer: {$retailer->name}");
                
                // Check if we have warehouses
                $warehouses = Warehouse::all();
                $this->info("Found {$warehouses->count()} warehouses");
                
                if ($warehouses->count() > 0) {
                    $warehouse = $warehouses->first();
                    $this->info("Using warehouse: {$warehouse->name}");
                    
                    // Check if we have products
                    $products = Product::all();
                    $this->info("Found {$products->count()} products");
                    
                    if ($products->count() > 0) {
                        $product = $products->first();
                        $this->info("Using product: {$product->name}");
                        
                        // Check if we have stock transfers
                        $stockTransfers = StockTransfer::where('to_location_type', Retailer::class)
                            ->where('to_location_id', $retailer->id)
                            ->where('status', 'completed')
                            ->get();
                        
                        $this->info("Found {$stockTransfers->count()} completed stock transfers to this retailer");
                        
                        if ($stockTransfers->count() > 0) {
                            $stockTransfer = $stockTransfers->first();
                            $this->info("Using stock transfer: {$stockTransfer->id}");
                            
                            // Test creating a retailer return
                            $returnData = [
                                'stock_transfer_id' => $stockTransfer->id,
                                'warehouse_id' => $warehouse->id,
                                'product_id' => $product->id,
                                'quantity' => 1,
                                'return_date' => now(),
                                'return_reason' => 'Test retailer return'
                            ];
                            
                            $this->info("Creating retailer return with data:");
                            $this->table(['Field', 'Value'], [
                                ['stock_transfer_id', $returnData['stock_transfer_id']],
                                ['warehouse_id', $returnData['warehouse_id']],
                                ['product_id', $returnData['product_id']],
                                ['quantity', $returnData['quantity']],
                                ['return_reason', $returnData['return_reason']],
                            ]);
                            
                            // Create the return
                            $return = StockTransaction::create([
                                'product_id' => $returnData['product_id'],
                                'location_id' => $retailer->id,
                                'location_type' => get_class($retailer),
                                'quantity' => $returnData['quantity'],
                                'direction' => 'outbound',
                                'transaction_type' => StockTransaction::TYPE_RETAILER_RETURN,
                                'reference_type' => StockTransfer::class,
                                'reference_id' => $returnData['stock_transfer_id'],
                                'transaction_date' => $returnData['return_date'],
                                'status' => StockTransaction::STATUS_ISSUED,
                                'notes' => $returnData['return_reason'],
                            ]);
                            
                            $this->info("Retailer return created successfully!");
                            $this->info("Return ID: {$return->id}");
                            $this->info("Status: {$return->status}");
                            $this->info("Transaction Type: {$return->transaction_type}");
                            
                            // Test approval
                            $this->newLine();
                            $this->info("Testing approval...");
                            $return->approve(1); // User ID 1
                            $this->info("Return approved successfully!");
                            $this->info("New Status: {$return->status}");
                            
                            $this->newLine();
                            $this->info("Test completed successfully!");
                            
                        } else {
                            $this->warn("No completed stock transfers found for this retailer");
                        }
                    } else {
                        $this->warn("No products found");
                    }
                } else {
                    $this->warn("No warehouses found");
                }
            } else {
                $this->warn("No retailers found");
            }
            
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());
        }
    }
}
