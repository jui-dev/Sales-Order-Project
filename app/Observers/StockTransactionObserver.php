<?php

namespace App\Observers;

use App\Models\StockTransaction;
use App\Models\Product;

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
     */
    private function updateProductAvailableStock(StockTransaction $transaction): void
    {
        $product = $transaction->product;
        if (!$product) {
            return;
        }

        // Calculate available stock purely from product_stocks table only
        // This avoids double-counting issues with stock_transactions
        $availableStock = (int) $product->stockBalances()->sum('quantity');
        $availableStock = max(0, $availableStock);

        // Update the product's available_stocks
        $product->available_stocks = $availableStock;
        $product->saveQuietly();
    }
} 