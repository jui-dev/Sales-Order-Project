<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A selling price per vendor cost, with one of them marked as the one
     * orders actually charge.
     *
     * The same product bought at 400 and at 200 justifies two different selling
     * prices, and seeing both side by side is how the margin on each becomes
     * legible. So a sale list may now hold several in-force rows for one
     * product, told apart by the purchase quote each was derived from.
     *
     * Which one a sales order charges cannot be inferred: stock is pooled, so a
     * unit on the shelf carries no vendor identity. Rather than guess, exactly
     * one row is flagged - an explicit choice beats a rule nobody can predict.
     */
    public function up(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->boolean('is_charged')->default(false)->after('is_auto_derived');

            // The resolver filters sale rows on this, so it is worth an index.
            $table->index(['price_list_id', 'product_id', 'is_charged'], 'pli_charged_idx');
        });

        // Every sale row in force today is the only one for its product, so it
        // is by definition the one being charged.
        $saleListIds = DB::table('price_lists')->where('type', 'sale')->pluck('id');

        DB::table('price_list_items')
            ->whereIn('price_list_id', $saleListIds)
            ->whereNull('ends_at')
            ->update(['is_charged' => true]);
    }

    public function down(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->dropIndex('pli_charged_idx');
            $table->dropColumn('is_charged');
        });
    }
};
