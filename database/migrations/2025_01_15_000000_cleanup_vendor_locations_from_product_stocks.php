<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove any vendor location records from product_stocks table
        // Vendors are external entities and should not be tracked in internal inventory
        DB::table('product_stocks')
            ->where('location_type', 'App\\Models\\Vendor')
            ->delete();

        // Also remove any other external location types that might exist
        DB::table('product_stocks')
            ->whereNotIn('location_type', [
                'App\\Models\\Warehouse',
                'App\\Models\\Retailer'
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible as it removes data
        // We cannot restore vendor location records as they should not exist
    }
};