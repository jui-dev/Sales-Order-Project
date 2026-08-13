<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added this import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // Skip for SQLite or other drivers unsupported by ADD CONSTRAINT
        }

        // Add a check constraint to ensure vendor return transactions have warehouse location type
        // This is a safeguard to prevent incorrect location types in the future
        DB::statement("
            ALTER TABLE stock_transactions 
            ADD CONSTRAINT check_vendor_return_location_type 
            CHECK (
                (transaction_type != 'vendor_return') OR 
                (transaction_type = 'vendor_return' AND location_type = 'App\\\\Models\\\\Warehouse')
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the check constraint
        DB::statement("
            ALTER TABLE stock_transactions 
            DROP CONSTRAINT check_vendor_return_location_type
        ");
    }
};
