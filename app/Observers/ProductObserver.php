<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "saving" event.
     *
     * Automatically calculates the selling price and gross profit whenever
     * the product is created or updated. The calculation uses either the
     * product-specific profit_margin (percentage) or falls back to the
     * global default configured in config/pricing.php. The margin is always
     * interpreted as a percentage (e.g., 25 => 25%).
     */
    public function saving(Product $product): void
    {
        // Determine the profit margin – prefer the product-specific one if set
        $margin = $product->profit_margin ?? null;
        if ($margin === null) {
            // Convert decimal (0.25) to percentage (25) if necessary in config
            $configured = config('pricing.default_profit_margin', 0.25);
            $margin = $configured > 1 ? $configured : $configured * 100;
            $product->profit_margin = $margin;
        }

        // Ensure we have a purchase price; if not, skip calculation
        if ($product->purchase_price !== null) {
            // Convert the percentage to factor (e.g., 25 => 0.25)
            $factor = $margin / 100;
            $product->selling_price = round($product->purchase_price * (1 + $factor), 2);
            $product->gross_profit  = round($product->selling_price - $product->purchase_price, 2);
            $product->last_price_update = now();
        }

        // Always enable auto pricing by default if not explicitly set
        if ($product->auto_pricing_enabled === null) {
            $product->auto_pricing_enabled = true;
        }
    }
} 