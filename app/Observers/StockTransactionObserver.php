<?php

namespace App\Observers;

use App\Models\StockTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockTransactionObserver
{
    /**
     * Update product available_stocks when a stock transaction is created
     */
    public function created(StockTransaction $transaction): void
    {
        // Call the model's updateProductStock method to update product_stocks table
        $transaction->updateProductStock();

        // Then update the product's available_stocks
        $this->updateProductAvailableStock($transaction);
    }

    /**
     * Update product available_stocks when a stock transaction is updated
     */
    public function updated(StockTransaction $transaction): void
    {
        // Only update if quantity or status changed
        if ($transaction->wasChanged(['quantity', 'status', 'direction'])) {
            // Call the model's updateProductStock method to update product_stocks table
            $transaction->updateProductStock();

            // Then update the product's available_stocks
            $this->updateProductAvailableStock($transaction);
        }
    }

    /**
     * Update product available_stocks when a stock transaction is deleted
     */
    public function deleted(StockTransaction $transaction): void
    {
        // Call the model's updateProductStock method to update product_stocks table
        $transaction->updateProductStock();

        // Then update the product's available_stocks
        $this->updateProductAvailableStock($transaction);
    }

    /**
     * Update the product's available_stocks based on product_stocks table only
     * Available stock = Total stock - Reserved stock
     */
    private function updateProductAvailableStock(StockTransaction $transaction): void
    {
        $product = $transaction->product;
        if (!$product) {
            return;
        }

        // Calculate available stock from product_stocks table accounting for reservations
        // Available stock = Total stock - Reserved stock
        DB::transaction(function() use ($product) {
            $stockBalances = $product->stockBalances()
                ->whereIn('location_type', ['App\\Models\\Warehouse', 'App\\Models\\Retailer'])
                ->get();

            $totalStock = $stockBalances->sum('quantity');
            $reservedStock = $stockBalances->sum('reserved_quantity');

            $availableStock = max(0, $totalStock - $reservedStock);

            // Update the product's available_stocks only if it has changed
            if ($product->available_stocks !== $availableStock) {
                $product->available_stocks = $availableStock;
                $product->saveQuietly();
            }
        });
    }
}
