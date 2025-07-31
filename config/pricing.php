<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Markup
    |--------------------------------------------------------------------------
    |
    | This value is used as the default markup for all products when
    | calculating selling prices from purchase prices. Value should be a
    | percentage (e.g., 25 for 25%).
    |
    */

    'default_markup' => 25, // Fixed 25% markup

    /*
    |--------------------------------------------------------------------------
    | Auto Update Selling Price
    |--------------------------------------------------------------------------
    |
    | When enabled, the system will automatically calculate and update selling
    | prices when supplies are completed and purchase prices are updated.
    |
    */

    'auto_update_selling_price' => true, // Always enable auto-updating

    /*
    |--------------------------------------------------------------------------
    | Always Update Selling Price
    |--------------------------------------------------------------------------
    |
    | When enabled, the system will always update selling prices even if they
    | are already set. When disabled, selling prices are only updated if they
    | are 0 or null.
    |
    */

    'always_update_selling_price' => true, // Always update selling price when purchase price changes

    /*
    |--------------------------------------------------------------------------
    | Product-Specific Markups
    |--------------------------------------------------------------------------
    |
    | You can define different markups for different product categories
    | or specific products here. Use product IDs or category names as keys.
    |
    */

    'product_markups' => [
        // Examples:
        // 'electronics' => 25,
        // 'furniture' => 30,
        // 'books' => 15,
        // 'product_id_123' => 35,
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Selling Price
    |--------------------------------------------------------------------------
    |
    | Ensure selling prices never go below a certain threshold relative to
    | purchase price. Set to 1.0 to ensure selling price is never below
    | purchase price.
    |
    */

    'minimum_selling_price_multiplier' => env('MINIMUM_SELLING_PRICE_MULTIPLIER', 1.05), // 5% minimum margin

]; 