<?php

namespace App\Observers;

use App\Models\SupplyItem;

class SupplyItemObserver
{
    /**
     * Handle the SupplyItem "created" event.
     *
     * Whenever a vendor supplies a product, we treat the unit_cost as the
     * latest purchase price for that product. We update the product's
     * purchase_price attribute, which will trigger ProductObserver
     * to recalculate selling price and gross profit using the markup formula.
     */
    public function created(SupplyItem $item): void
    {
        if ($item->product) {
            $product = $item->product;
            $product->purchase_price = $item->unit_cost;
            
            // Ensure auto-pricing is enabled
            $product->auto_pricing_enabled = true;
            
            // Persist the change — ProductObserver will kick in automatically
            // and calculate: selling_price = purchase_price + (purchase_price * markup %)
            $product->save();
        }
    }
} 