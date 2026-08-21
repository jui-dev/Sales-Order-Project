<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sale prices split by where the goods are fulfilled from, and each one
     * records the purchase price it was worked out from.
     *
     * Two changes, both about making the margin legible:
     *
     * 1. A warehouse sale and a retailer sale can be charged differently.
     *    Retailers are part of the business - moving stock out to one is not a
     *    sale - but an order fulfilled FROM a retailer may be priced
     *    differently from one shipped out of the warehouse. Assignments are
     *    made against the location CLASS with a null id, meaning "any of these",
     *    so opening a new store needs no pricing work.
     *
     * 2. A sale price row now carries the markup it was derived at and the
     *    vendor quote it was derived from. Stock is pooled - a unit on the
     *    shelf has no vendor identity - so the basis does not decide what is
     *    charged. It records what the price was reasoned from, which is what
     *    makes the gross profit on the screen mean something.
     */
    public function up(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            // Null means the price was typed in rather than derived.
            $table->decimal('markup_percent', 8, 2)->nullable()->after('min_quantity');
            // The purchase-list row this was worked out from. nullOnDelete
            // because losing the basis must not delete the price it produced.
            $table->foreignId('basis_price_list_item_id')->nullable()->after('markup_percent')
                ->constrained('price_list_items')->nullOnDelete();
            // Whether the price should keep following its basis. Unticking is
            // how a user pins a figure that markup would otherwise recompute.
            $table->boolean('is_auto_derived')->default(false)->after('basis_price_list_item_id');
        });

        $now = Carbon::now();

        // The existing default sale list becomes the warehouse one, so the
        // prices already on it keep applying to warehouse fulfilment rather
        // than being orphaned.
        $warehouseListId = DB::table('price_lists')->where('code', 'retail')->value('id');

        if ($warehouseListId) {
            DB::table('price_lists')->where('id', $warehouseListId)->update([
                'name' => 'Warehouse Sales',
                'code' => 'sale-warehouse',
                'notes' => 'What we charge when an order is fulfilled from a warehouse.',
                'updated_at' => $now,
            ]);
        } else {
            $warehouseListId = DB::table('price_lists')->insertGetId([
                'name' => 'Warehouse Sales',
                'code' => 'sale-warehouse',
                'type' => 'sale',
                'currency' => 'USD',
                'priority' => 10,
                'is_default' => true,
                'is_active' => true,
                'notes' => 'What we charge when an order is fulfilled from a warehouse.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $retailerListId = DB::table('price_lists')->where('code', 'sale-retailer')->value('id')
            ?: DB::table('price_lists')->insertGetId([
                'name' => 'Retailer Sales',
                'code' => 'sale-retailer',
                'type' => 'sale',
                'currency' => 'USD',
                // Above the warehouse list so it wins when a retailer is the
                // fulfilment location; the warehouse list stays the fallback.
                'priority' => 20,
                'is_default' => false,
                'is_active' => true,
                'notes' => 'What we charge when an order is fulfilled from a retailer store.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        // Class-level assignments: null id means "any location of this kind".
        foreach ([
            [$warehouseListId, \App\Models\Warehouse::class],
            [$retailerListId, \App\Models\Retailer::class],
        ] as [$listId, $type]) {
            $exists = DB::table('price_list_assignments')
                ->where('price_list_id', $listId)
                ->where('assignable_type', $type)
                ->whereNull('assignable_id')
                ->exists();

            if (! $exists) {
                DB::table('price_list_assignments')->insert([
                    'price_list_id' => $listId,
                    'assignable_type' => $type,
                    'assignable_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Prices already on file were typed by nobody - they came from the old
        // selling_price column, itself derived at the product's markup. Record
        // that markup so the screen can show where the figure came from,
        // without switching them to auto-derive and moving them.
        // Row by row rather than an UPDATE ... JOIN, which SQLite (used by the
        // test suite) does not support.
        $markups = DB::table('products')->pluck('markup', 'id');

        DB::table('price_list_items')
            ->where('price_list_id', $warehouseListId)
            ->whereNull('markup_percent')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($markups) {
                foreach ($rows as $row) {
                    $markup = $markups[$row->product_id] ?? null;

                    if ($markup === null) {
                        continue;
                    }

                    DB::table('price_list_items')
                        ->where('id', $row->id)
                        ->update(['markup_percent' => $markup]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('price_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('basis_price_list_item_id');
            $table->dropColumn(['markup_percent', 'is_auto_derived']);
        });

        DB::table('price_lists')->where('code', 'sale-warehouse')->update([
            'name' => 'Retail',
            'code' => 'retail',
        ]);

        $retailerListId = DB::table('price_lists')->where('code', 'sale-retailer')->value('id');

        if ($retailerListId) {
            DB::table('price_list_items')->where('price_list_id', $retailerListId)->delete();
            DB::table('price_list_assignments')->where('price_list_id', $retailerListId)->delete();
            DB::table('price_lists')->where('id', $retailerListId)->delete();
        }
    }
};
