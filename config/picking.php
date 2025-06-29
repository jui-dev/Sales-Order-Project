<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automatic Picking Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for automatic picking list
    | generation when vendors supply products to the warehouse.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto-Generate Picking Lists
    |--------------------------------------------------------------------------
    |
    | When enabled, picking lists will be automatically generated when
    | a supply is marked as completed.
    |
    */
    'auto_generate_on_supply_completion' => env('PICKING_AUTO_GENERATE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-Complete Picking Lists
    |--------------------------------------------------------------------------
    |
    | When enabled, automatically generated picking lists will be marked
    | as completed immediately, bypassing manual picking process.
    | Set to false for manual warehouse receiving workflow.
    |
    */
    'auto_complete_picking_lists' => env('PICKING_AUTO_COMPLETE', false),

    /*
    |--------------------------------------------------------------------------
    | Default Receiving Location
    |--------------------------------------------------------------------------
    |
    | The default warehouse location ID for receiving vendor supplies.
    | If not set, the system will use the default warehouse.
    |
    */
    'default_receiving_location' => env('PICKING_DEFAULT_LOCATION', null),

    /*
    |--------------------------------------------------------------------------
    | Require Manual Confirmation
    |--------------------------------------------------------------------------
    |
    | When enabled, users must manually confirm the picking list
    | before stock movements are processed.
    |
    */
    'require_manual_confirmation' => env('PICKING_REQUIRE_CONFIRMATION', true),

    /*
    |--------------------------------------------------------------------------
    | Picking List Types for Supplies
    |--------------------------------------------------------------------------
    |
    | Configuration for different types of picking lists that can be
    | generated for vendor supplies.
    |
    */
    'supply_picking_types' => [
        'receiving' => [
            'name' => 'Warehouse Receiving',
            'description' => 'Manual receiving process with pending picking lists',
            'auto_complete' => false,
            'requires_confirmation' => true,
        ],
        'direct_stocking' => [
            'name' => 'Direct Stocking',
            'description' => 'Automatic stocking without manual picking',
            'auto_complete' => true,
            'requires_confirmation' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for automatic picking list generation.
    |
    */
    'notifications' => [
        'notify_on_generation' => env('PICKING_NOTIFY_GENERATION', true),
        'notify_on_completion' => env('PICKING_NOTIFY_COMPLETION', true),
        'notification_channels' => ['mail', 'database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Validation rules for automatic picking list generation.
    |
    */
    'validation' => [
        'min_supply_value' => env('PICKING_MIN_SUPPLY_VALUE', 0),
        'require_active_warehouse' => true,
        'check_stock_capacity' => false,
    ],
]; 