<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move the prices already on file into the new structure.
     *
     * Three sources, three destinations:
     *
     *  - products.selling_price  -> a single default "Retail" sale list
     *  - vendor_products.unit_cost -> one purchase list per vendor
     *  - products.purchase_price -> the opening balance of the cost ledger
     *
     * Effective dates are recovered from last_price_update where the column has
     * one, so a product priced months ago does not look like it was priced
     * today. Where it is missing, created_at is the closest honest answer.
     *
     * What this cannot do is invent history. Only the current value of each
     * price was ever stored, so every product gets exactly one opening row.
     * From here on the record accumulates properly.
     *
     * Idempotent: the lists are keyed by code and the rows are only inserted
     * where none exists, so re-running adds nothing.
     */
    public function up(): void
    {
        $now = Carbon::now();

        $retailListId = $this->retailList($now);
        $this->seedRetailPrices($retailListId, $now);
        $this->seedVendorPurchaseLists($now);
        $this->replayCostLedger($now);
    }

    /**
     * The base sale list: no assignments, so it applies to everybody, and
     * is_default so the resolver falls back to it.
     */
    private function retailList(Carbon $now): int
    {
        $existing = DB::table('price_lists')->where('code', 'retail')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('price_lists')->insertGetId([
            'name' => 'Retail',
            'code' => 'retail',
            'type' => 'sale',
            'currency' => 'USD',
            'priority' => 0,
            'is_default' => true,
            'is_active' => true,
            'notes' => 'Created automatically from products.selling_price.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedRetailPrices(int $listId, Carbon $now): void
    {
        DB::table('products')
            ->where('selling_price', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($listId, $now) {
                foreach ($products as $product) {
                    $exists = DB::table('price_list_items')
                        ->where('price_list_id', $listId)
                        ->where('product_id', $product->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('price_list_items')->insert([
                        'price_list_id' => $listId,
                        'product_id' => $product->id,
                        'unit_price' => $product->selling_price,
                        'min_quantity' => 1,
                        // The date the price actually took effect, not today.
                        'starts_at' => $product->last_price_update ?? $product->created_at ?? $now,
                        'ends_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * One purchase list per vendor that has agreed a price with us, assigned to
     * that vendor so PriceResolver::forPurchase finds it.
     *
     * Rows with a null unit_cost are skipped on purpose: "carried but not yet
     * priced" is a real state that vendor_products deliberately distinguishes
     * from free, and inventing a zero here would erase that.
     */
    private function seedVendorPurchaseLists(Carbon $now): void
    {
        $vendorIds = DB::table('vendor_products')
            ->whereNotNull('unit_cost')
            ->distinct()
            ->pluck('vendor_id');

        foreach ($vendorIds as $vendorId) {
            $vendor = DB::table('vendors')->find($vendorId);

            if (! $vendor) {
                continue;
            }

            $code = 'vendor-'.$vendorId;
            $listId = DB::table('price_lists')->where('code', $code)->value('id');

            if (! $listId) {
                $listId = DB::table('price_lists')->insertGetId([
                    'name' => 'Vendor: '.$vendor->name,
                    'code' => $code,
                    'type' => 'purchase',
                    'currency' => 'USD',
                    'priority' => 0,
                    'is_default' => false,
                    'is_active' => true,
                    'notes' => 'Created automatically from vendor_products.unit_cost.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('price_list_assignments')->insert([
                    'price_list_id' => $listId,
                    'assignable_type' => \App\Models\Vendor::class,
                    'assignable_id' => $vendorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rows = DB::table('vendor_products')
                ->where('vendor_id', $vendorId)
                ->whereNotNull('unit_cost')
                ->get();

            foreach ($rows as $row) {
                $exists = DB::table('price_list_items')
                    ->where('price_list_id', $listId)
                    ->where('product_id', $row->product_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('price_list_items')->insert([
                    'price_list_id' => $listId,
                    'product_id' => $row->product_id,
                    'unit_price' => $row->unit_cost,
                    'min_quantity' => 1,
                    'starts_at' => $row->updated_at ?? $row->created_at ?? $now,
                    'ends_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Rebuild the cost ledger by replaying the deliveries that actually happened.
     *
     * products.purchase_price only ever held the *last* receipt's unit cost, so
     * copying it forward would enshrine the very error this ledger exists to
     * correct - the live data has 55 units carried at 200 when 50 of them cost
     * 400. But every posted GRN is still on file with its quantity and cost, so
     * the true weighted average is recoverable rather than merely frozen.
     *
     * Replayed in receipt order, so the resulting rows are the same ones the
     * system would have written had it been averaging all along, and the cost
     * on any past date reads correctly.
     */
    private function replayCostLedger(Carbon $now): void
    {
        $lines = DB::table('grns')
            ->join('supplies', 'supplies.id', '=', 'grns.supply_id')
            ->join('supply_items', 'supply_items.supply_id', '=', 'supplies.id')
            ->where('grns.status', 'posted')
            ->where('supply_items.unit_cost', '>', 0)
            ->orderBy('grns.received_date')
            ->orderBy('grns.id')
            ->orderBy('supply_items.id')
            ->get([
                'grns.id as grn_id',
                'grns.received_date',
                'supply_items.product_id',
                'supply_items.quantity',
                'supply_items.unit_cost',
            ]);

        // Products already carrying a ledger, resolved once up front. Testing
        // this inside the loop would skip every receipt after a product's first,
        // leaving the average struck over one delivery instead of all of them.
        $alreadyLedgered = DB::table('product_costs')->distinct()->pluck('product_id')->flip();

        // Running average per product, carried across the replay.
        $held = [];
        $seeded = [];

        foreach ($lines as $line) {
            $productId = (int) $line->product_id;

            if ($alreadyLedgered->has($productId)) {
                continue;
            }

            $priorQty = $held[$productId]['quantity'] ?? 0;
            $priorCost = $held[$productId]['cost'] ?? 0.0;

            $incomingQty = max(0, (int) $line->quantity);
            $totalQty = $priorQty + $incomingQty;

            $newCost = $totalQty > 0
                ? (($priorQty * $priorCost) + ($incomingQty * (float) $line->unit_cost)) / $totalQty
                : (float) $line->unit_cost;

            $held[$productId] = ['quantity' => $totalQty, 'cost' => $newCost];
            $seeded[$productId] = true;

            DB::table('product_costs')->insert([
                'product_id' => $productId,
                'unit_cost' => round($newCost, 4),
                'quantity_on_hand' => $totalQty,
                'effective_at' => $line->received_date,
                'source_type' => \App\Models\Grn::class,
                'source_id' => $line->grn_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->seedUnreceivedProducts($seeded, $now);
    }

    /**
     * Opening balance for products with no posted receipt to replay.
     *
     * Their cost was set by an import, a seeder or by hand, so purchase_price is
     * the only figure there is. quantity_on_hand comes from available_stocks so
     * the next real delivery averages against the stock actually held rather
     * than treating the warehouse as empty and adopting the new price outright.
     *
     * @param  array<int, bool>  $alreadySeeded
     */
    private function seedUnreceivedProducts(array $alreadySeeded, Carbon $now): void
    {
        DB::table('products')
            ->where('purchase_price', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($alreadySeeded, $now) {
                foreach ($products as $product) {
                    if (isset($alreadySeeded[$product->id])) {
                        continue;
                    }

                    $exists = DB::table('product_costs')
                        ->where('product_id', $product->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('product_costs')->insert([
                        'product_id' => $product->id,
                        'unit_cost' => $product->purchase_price,
                        'quantity_on_hand' => max(0, (int) ($product->available_stocks ?? 0)),
                        'effective_at' => $product->last_price_update ?? $product->created_at ?? $now,
                        'source_type' => null,
                        'source_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * Removes the lists this migration created, and empties the cost ledger.
     *
     * The ledger is cleared wholesale rather than selectively: a replayed row
     * and one written by a later GRN are the same shape, so there is no honest
     * way to tell them apart. Rolling back after the system has been recording
     * costs therefore discards them - which is why this is a development
     * affordance, not a production undo. The tables themselves belong to
     * 2026_08_21_000100_create_pricing_tables.
     */
    public function down(): void
    {
        $listIds = DB::table('price_lists')
            ->where('code', 'retail')
            ->orWhere('code', 'like', 'vendor-%')
            ->pluck('id');

        DB::table('price_list_items')->whereIn('price_list_id', $listIds)->delete();
        DB::table('price_list_assignments')->whereIn('price_list_id', $listIds)->delete();
        DB::table('price_lists')->whereIn('id', $listIds)->delete();
        DB::table('product_costs')->delete();
    }
};
