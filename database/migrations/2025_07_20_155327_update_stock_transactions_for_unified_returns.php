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
        // Update any existing 'return' transactions to 'customer_return' for backward compatibility
        DB::table('stock_transactions')
            ->where('transaction_type', 'return')
            ->update(['transaction_type' => 'customer_return']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any customer_return transactions back to 'return'
        DB::table('stock_transactions')
            ->where('transaction_type', 'customer_return')
            ->update(['transaction_type' => 'return']);
    }
};
