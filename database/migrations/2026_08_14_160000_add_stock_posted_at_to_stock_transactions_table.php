<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the moment a transaction's stock effect was applied to
     * product_stocks. Replaces the old 1-hour cache key, which expired and let
     * the same transaction post its stock more than once.
     */
    public function up(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->timestamp('stock_posted_at')->nullable()->after('status');
            $table->index('stock_posted_at');
        });

        // Backfill: any transaction that already moved stock is marked as
        // posted, so the reconciliation command does not read historical rows
        // as still awaiting their stock effect.
        DB::table('stock_transactions')
            ->whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
            ->whereIn('status', ['approved', 'completed'])
            ->update(['stock_posted_at' => DB::raw('updated_at')]);

        // Non-return transactions post their stock on creation, so all of them
        // have already been applied.
        DB::table('stock_transactions')
            ->whereNotIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
            ->update(['stock_posted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropIndex(['stock_posted_at']);
            $table->dropColumn('stock_posted_at');
        });
    }
};
