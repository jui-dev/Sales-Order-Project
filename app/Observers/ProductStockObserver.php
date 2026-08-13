<?php

namespace App\Observers;

use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;

class ProductStockObserver
{
    /**
     * Handle the ProductStock "created" event.
     */
    public function created(ProductStock $productStock): void
    {
        $this->updateProductAvailableStock($productStock);
    }

    /**
     * Handle the ProductStock "updated" event.
     */
    public function updated(ProductStock $productStock): void
    {
        // Only update if quantity or reserved_quantity changed
        if ($productStock->wasChanged(['quantity', 'reserved_quantity'])) {
            $this->updateProductAvailableStock($productStock);
        }
    }

    /**
     * Handle the ProductStock "deleted" event.
     */
    public function deleted(ProductStock $productStock): void
    {
        $this->updateProductAvailableStock($productStock);
    }

    /**
     * Update the product's available_stocks when stock or reservations change
     */
    private function updateProductAvailableStock(ProductStock $productStock): void
    {
        $product = $productStock->product;
        if (!$product) {
            return;
        }

        // Calculate available stock from product_stocks table accounting for reservations
        // Available stock = Total stock - Reserved stock
        DB::transaction(function() use ($product) {
            $stockData = DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->whereIn('location_type', ['App\\Models\\Warehouse', 'App\\Models\\Retailer'])
                ->selectRaw('SUM(quantity) as total_stock, SUM(COALESCE(reserved_quantity, 0)) as reserved_stock')
                ->first();

            $totalStock = $stockData->total_stock ?? 0;
            $reservedStock = $stockData->reserved_stock ?? 0;
            $availableStock = max(0, $totalStock - $reservedStock);

            // Update the product's available_stocks only if it has changed
            if ($product->available_stocks !== $availableStock) {
                $product->available_stocks = $availableStock;
                $product->saveQuietly();
            }
        });
    }
}
