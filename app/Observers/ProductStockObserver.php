<?php

namespace App\Observers;

use App\Models\ProductStock;
use App\Models\StockTransaction;

class ProductStockObserver
{
    /**
     * Sync the parent product\'s aggregate stock whenever a stock row is saved.
     */
    public function saved(ProductStock $stock): void
    {
        $this->syncProductStock($stock);
    }

    /**
     * Sync the parent product\'s aggregate stock whenever a stock row is deleted.
     */
    public function deleted(ProductStock $stock): void
    {
        $this->syncProductStock($stock);
    }

    private function syncProductStock(ProductStock $stock): void
    {
        // Guard: if the relation is missing, bail early.
        if (! $stock->product) {
            return;
        }

        // Calculate available stock purely from product_stocks table only
        // This avoids double-counting issues with stock_transactions
        $availableStock = (int) ProductStock::where('product_id', $stock->product_id)
            ->sum('quantity');
        $availableStock = max(0, $availableStock);

        // Persist quietly to avoid triggering observers / events.
        $stock->product->available_stocks = $availableStock;
        $stock->product->saveQuietly();
    }
} 