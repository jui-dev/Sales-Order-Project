<?php

namespace App\Observers;

use App\Models\SupplyItem;

class SupplyItemObserver
{
    /**
     * Handle the SupplyItem "created" event.
     *
     * Whenever a vendor supplies a product, we treat the unit_cost as the
     * latest purchase price for that product. We simply update the product's
     * purchase_price attribute, which will in turn trigger ProductObserver
     * to recalculate selling price and gross profit.
     */
    public function created(SupplyItem $item): void
    {
        if ($item->product) {
            $product = $item->product;
            $product->purchase_price = $item->unit_cost;
            // Persist the change — ProductObserver will kick in automatically
            $product->save();
        }
    }
} 