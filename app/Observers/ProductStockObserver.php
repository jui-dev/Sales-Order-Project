<?php

namespace App\Observers;

use App\Models\ProductStock;

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

        $totalAvailable = ProductStock::where('product_id', $stock->product_id)
            ->get()
            ->sum(fn (ProductStock $s) => $s->quantity - $s->reserved_quantity);

        // Persist quietly to avoid triggering observers / events.
        $stock->product->available_stocks = $totalAvailable;
        $stock->product->saveQuietly();
    }
} 