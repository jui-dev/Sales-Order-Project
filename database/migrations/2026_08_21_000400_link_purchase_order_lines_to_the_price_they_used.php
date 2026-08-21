<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which vendor quote a purchase order line was priced from.
     *
     * Sales lines already carry price_list_item_id. Purchase lines did not, so
     * "has this cost actually been used for anything?" was unanswerable - and
     * that is the question a price has to answer before it can be locked
     * against editing.
     *
     * The line keeps its own unit_cost regardless; this is provenance, not the
     * price itself. Deleting a price row must never delete the order that used
     * it, hence nullOnDelete.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('price_list_item_id')->nullable()->after('unit_cost')
                ->constrained('price_list_items')->nullOnDelete();
        });

        $this->backfillFromMatchingQuotes();
    }

    /**
     * Point existing lines at the quote they were almost certainly priced from.
     *
     * Matched on vendor, product and an identical cost. Deliberately
     * conservative: where the figures do not match exactly the line is left
     * unlinked rather than guessed at, because a wrong link would lock the
     * wrong price. An unlinked line simply means that quote reads as unused,
     * which errs towards letting a price be edited rather than freezing one
     * that was never really used.
     */
    private function backfillFromMatchingQuotes(): void
    {
        $lines = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereNull('purchase_order_items.price_list_item_id')
            ->get([
                'purchase_order_items.id',
                'purchase_order_items.product_id',
                'purchase_order_items.unit_cost',
                'purchase_orders.vendor_id',
            ]);

        foreach ($lines as $line) {
            $listId = DB::table('price_lists')
                ->where('code', 'vendor-'.$line->vendor_id)
                ->where('type', 'purchase')
                ->value('id');

            if (! $listId) {
                continue;
            }

            $itemId = DB::table('price_list_items')
                ->where('price_list_id', $listId)
                ->where('product_id', $line->product_id)
                ->where('unit_price', $line->unit_cost)
                ->orderBy('starts_at')
                ->value('id');

            if ($itemId) {
                DB::table('purchase_order_items')
                    ->where('id', $line->id)
                    ->update(['price_list_item_id' => $itemId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_item_id');
        });
    }
};
