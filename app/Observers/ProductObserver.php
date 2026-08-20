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
     *
     * purchase_price is written in exactly one place - GrnService, when a GRN is
     * posted and the goods physically arrive. Recording or ordering a supply
     * does not reach here, so merely placing an order cannot reprice a product.
     */
    public function saving(Product $product): void
    {
        // Determine the markup – prefer the product-specific one if set
        $markup = $product->markup ?? null;
        if ($markup === null) {
            // Markup is the source of truth for pricing. When a caller supplies
            // both prices but no markup - a seeder, a factory, an import - infer
            // it from them rather than overwriting the price they asked for.
            $markup = $this->inferMarkup($product) ?? $this->defaultMarkup();

            // Only set markup if the column exists (for test environments)
            if (Schema::hasColumn('products', 'markup')) {
                $product->markup = $markup;
            }
        }

        // Normalise the flag before it is read below - a model that has never
        // had the attribute set reads as null, and testing null would skip the
        // derivation on the very first save.
        if ($product->auto_pricing_enabled === null) {
            $product->auto_pricing_enabled = true;
        }

        // Skip until there is a real cost to derive from.
        //
        // purchase_price is NOT NULL DEFAULT 0.00, so a product that has never
        // been received reads as 0.00 rather than null. Deriving from that would
        // set selling_price to 0.00 on every save - so a zero cost means "not
        // priced yet" and the existing selling price is left alone.
        if ((float) $product->purchase_price > 0 && $product->auto_pricing_enabled) {
            // Convert the percentage to factor (e.g., 25 => 0.25)
            $factor = $markup / 100;
            // Formula: selling_price = purchase_price + (purchase_price * markup %)
            $product->selling_price = round($product->purchase_price + ($product->purchase_price * $factor), 2);
            $product->gross_profit  = round($product->selling_price - $product->purchase_price, 2);
            $product->last_price_update = now();
        }
    }

    /**
     * Work out the markup a caller-supplied pair of prices already implies.
     *
     * Returns null when there is nothing to infer from, or when the prices
     * describe a loss - a negative markup would make the formula reproduce that
     * loss on every future receipt.
     */
    private function inferMarkup(Product $product): ?float
    {
        $cost = (float) $product->purchase_price;
        $selling = (float) $product->selling_price;

        if ($cost <= 0 || $selling <= 0 || $selling < $cost) {
            return null;
        }

        return round(($selling - $cost) / $cost * 100, 2);
    }

    /**
     * The configured fallback, normalised to a percentage (0.25 => 25).
     */
    private function defaultMarkup(): float
    {
        $configured = config('pricing.default_markup', 25);

        return (float) ($configured > 1 ? $configured : $configured * 100);
    }
} 