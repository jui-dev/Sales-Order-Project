<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Return Management Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the return management system.
    |
    */

    // Maximum number of days after which returns are no longer allowed
    'max_return_days' => env('RETURN_MAX_DAYS', 30),

    // Default return status
    'default_status' => 'pending',

    // Allowed return statuses
    'allowed_statuses' => [
        'pending',
        'approved',
        'completed',
    ],
]; 