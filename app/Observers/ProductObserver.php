<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;

class ProductObserver
{
    /**
     * Handle the Product "saving" event.
     *
     * Automatically calculates the selling price and gross profit whenever
     * the product is created or updated. The calculation uses either the
     * product-specific markup (percentage) or falls back to the
     * global default configured in config/pricing.php. The markup is always
     * interpreted as a percentage (e.g., 25 => 25%).
     * 
     * Formula: selling_price = purchase_price + (purchase_price * markup %)
     */
    public function saving(Product $product): void
    {
        // Determine the markup – prefer the product-specific one if set
        $markup = $product->markup ?? null;
        if ($markup === null) {
            // Convert decimal (0.25) to percentage (25) if necessary in config
            $configured = config('pricing.default_markup', 25);
            $markup = $configured > 1 ? $configured : $configured * 100;
            
            // Only set markup if the column exists (for test environments)
            if (Schema::hasColumn('products', 'markup')) {
                $product->markup = $markup;
            }
        }

        // Ensure we have a purchase price; if not, skip calculation
        if ($product->purchase_price !== null && $product->auto_pricing_enabled) {
            // Convert the percentage to factor (e.g., 25 => 0.25)
            $factor = $markup / 100;
            // Formula: selling_price = purchase_price + (purchase_price * markup %)
            $product->selling_price = round($product->purchase_price + ($product->purchase_price * $factor), 2);
            $product->gross_profit  = round($product->selling_price - $product->purchase_price, 2);
            $product->last_price_update = now();
        }

        // Always enable auto pricing by default if not explicitly set
        if ($product->auto_pricing_enabled === null) {
            $product->auto_pricing_enabled = true;
        }
    }
} 