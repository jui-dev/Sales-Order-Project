<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductService;

class RecalculateProductStocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:recalculate-stocks {--product-id= : Recalculate for specific product ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate available_stocks for all products considering returns and other stock transactions';

    /**
     * Execute the console command.
     */
    public function handle(ProductService $productService)
    {
        $this->info('Starting product stock recalculation...');

        $productId = $this->option('product-id');

        if ($productId) {
            // Recalculate for specific product
            $this->info("Recalculating stock for product ID: {$productId}");
            
            try {
                $product = \App\Models\Product::findOrFail($productId);
                $oldStock = $product->available_stocks;
                
                $productService->recalculateProductStock($product);
                $product->refresh();
                
                $newStock = $product->available_stocks;
                $difference = $newStock - $oldStock;
                
                $this->info("Product: {$product->name} (ID: {$product->id})");
                $this->info("Old stock: {$oldStock}");
                $this->info("New stock: {$newStock}");
                $this->info("Difference: " . ($difference >= 0 ? '+' : '') . $difference);
                $this->info('Recalculation completed successfully!');
                
            } catch (\Exception $e) {
                $this->error("Error recalculating stock for product ID {$productId}: " . $e->getMessage());
                return 1;
            }
        } else {
            // Recalculate for all products
            $this->info('Recalculating stock for all products...');
            
            $results = $productService->recalculateAllProductStocks();
            
            $this->info("Total products processed: " . count($results));
            $this->info("Successfully updated: " . count($results));
            
            if (!empty($results)) {
                $this->info('All products updated successfully!');
                foreach ($results as $result) {
                    $this->line("Product: {$result['product_name']} (ID: {$result['product_id']}) - New stock: {$result['new_stock']}");
                }
            }
        }

        return 0;
    }
} 