<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockTransfer;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockTransferItem;

class SetupRetailerReturnTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:retailer-return-test-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup test data for retailer returns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up test data for retailer returns...');
        $this->newLine();

        try {
            // Get retailers and warehouses
            $retailers = Retailer::all();
            $warehouses = Warehouse::all();
            $products = Product::all();

            if ($retailers->count() === 0 || $warehouses->count() === 0 || $products->count() === 0) {
                $this->error('Need retailers, warehouses, and products to create test data');
                return;
            }

            $retailer = $retailers->first();
            $warehouse = $warehouses->first();
            $product = $products->first();

            $this->info("Using retailer: {$retailer->name}");
            $this->info("Using warehouse: {$warehouse->name}");
            $this->info("Using product: {$product->name}");

            // Create a stock transfer from warehouse to retailer
            $stockTransfer = StockTransfer::create([
                'from_location_type' => get_class($warehouse),
                'from_location_id' => $warehouse->id,
                'to_location_type' => get_class($retailer),
                'to_location_id' => $retailer->id,
                'transfer_date' => now(),
                'status' => 'completed',
                'notes' => 'Test stock transfer for retailer returns',
            ]);

            $this->info("Created stock transfer: {$stockTransfer->id}");

            // Create stock transfer items
            $stockTransferItem = StockTransferItem::create([
                'stock_transfer_id' => $stockTransfer->id,
                'product_id' => $product->id,
                'quantity' => 10,
            ]);

            $this->info("Created stock transfer item with 10 units of {$product->name}");

            $this->newLine();
            $this->info('Test data setup completed successfully!');
            $this->info("Stock Transfer ID: {$stockTransfer->id}");
            $this->info("You can now test retailer returns using: php artisan test:retailer-return");

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());
        }
    }
}
