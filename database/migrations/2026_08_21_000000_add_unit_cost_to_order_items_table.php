<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record what a sold line actually cost us, at the moment it was sold.
     *
     * order_items has always snapshotted unit_price but never the cost, so
     * OrderItem::getUnitCostAttribute fell back to the product's *current*
     * purchase_price - and since the column did not exist, that fallback fired
     * every time. Posting a GRN overwrites products.purchase_price outright, so
     * every historical order's COGS and profit silently re-computed against the
     * newest cost. That fed journal entries, returns and the profit report.
     *
     * The backfill can only freeze the figures at today's cost: the per-date
     * cost history was never recorded, so the pre-migration numbers cannot be
     * reconstructed. Freezing them is still strictly better than leaving them
     * to keep drifting with every future receipt.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Nullable: distinguishes "never captured" (pre-migration rows we
            // could not reconstruct) from a genuine zero cost.
            $table->decimal('unit_cost', 12, 4)->nullable()->after('unit_price');
        });

        // Freeze existing lines at the only cost still on file.
        DB::table('order_items')
            ->whereNull('unit_cost')
            ->update([
                'unit_cost' => DB::raw(
                    '(SELECT purchase_price FROM products WHERE products.id = order_items.product_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
