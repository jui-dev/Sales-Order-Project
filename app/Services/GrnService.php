<?php

namespace App\Services;

use App\Models\Grn;
use App\Models\Supply;
use App\Services\Pricing\PriceListService;
use App\Services\Pricing\ProductCostService;
use Illuminate\Support\Facades\DB;

class GrnService
{
    public function __construct(
        private readonly ProductCostService $costs,
        private readonly PriceListService $priceLists,
    ) {}

    /**
     * Transition a GRN to the supplied next status. When the status becomes
     * "posted" the corresponding stock transactions will be booked and the
     * product stock levels updated.
     */
    public function transitionStatus(int $grnId, string $toStatus): Grn
    {
        return DB::transaction(function () use ($grnId, $toStatus) {
            /** @var Grn $grn */
            $grn = Grn::with(['supply.vendor', 'supply.warehouse', 'supply.items.product'])->findOrFail($grnId);
            $from = $grn->status;

            // Early-out if already at desired state
            if ($from === $toStatus) {
                return $grn;
            }

            // Update the status first
            $grn->update(['status' => $toStatus]);

            // Only run stock posting logic when we reach the final state
            if ($toStatus === 'posted') {
                $this->postStock($grn);
            }

            return $grn;
        });
    }

    /**
     * Perform stock movements once the GRN is posted.
     *
     * We increase stock in the destination warehouse and create matching
     * stock transactions / transfer records so that the entire movement is
     * auditable.
     */
    private function postStock(Grn $grn): void
    {
        // Guard against double-posting
        if ($grn->posted_at ?? false) {
            return;
        }

        $supply = $grn->supply;
        $warehouse = $supply->warehouse;

        /* ------------------------------------------------------------
         * Create (or fetch) a StockTransfer that represents the inbound
         * movement from Vendor → Warehouse. Doing this upfront allows us
         * to avoid running firstOrCreate inside the loop.
         * ------------------------------------------------------------ */
        $transfer = \App\Models\StockTransfer::firstOrCreate([
            'from_location_id' => $supply->vendor_id,
            'from_location_type' => \App\Models\Vendor::class,
            'to_location_id' => $supply->warehouse_id,
            'to_location_type' => get_class($warehouse),
            'transfer_date' => $grn->received_date ?? now(),
        ], [
            'status' => 'completed',
            'notes' => 'Auto-generated from GRN #'.$grn->id,
        ]);

        foreach ($supply->items as $item) {
            // Check if this GRN's stock has already been posted
            $existingTransaction = \App\Models\StockTransaction::where([
                'product_id' => $item->product_id,
                'location_id' => $supply->warehouse_id,
                'location_type' => get_class($warehouse),
                'reference_type' => Grn::class,
                'reference_id' => $grn->id,
            ])->exists();

            // Only create transaction if it doesn't exist
            if (! $existingTransaction) {
                // Create stock transaction ledger - stock will be updated by the transaction
                \App\Models\StockTransaction::create([
                    'product_id' => $item->product_id,
                    'location_id' => $supply->warehouse_id,
                    'location_type' => get_class($warehouse),
                    'quantity' => $item->quantity,
                    'direction' => 'inbound',
                    'transaction_type' => \App\Models\StockTransaction::TYPE_STOCK_IN,
                    'reference_type' => Grn::class,
                    'reference_id' => $grn->id,
                    'transaction_date' => now(),
                    'status' => 'completed', // Stock is actually received when GRN is posted
                ]);

                // Receiving is the only moment a product's cost changes. Ordering
                // must not reprice the catalogue, so this deliberately lives here
                // rather than on SupplyItem::created, and inside the
                // already-posted guard so a re-post cannot reprice twice.
                $this->applyReceivedCost($item);
            }

            // Persist transfer item row (linked to the transfer created above)
            $transfer->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ]);
        }

        // Mark supply as completed
        if ($supply->status !== 'completed') {
            $supply->update(['status' => 'completed']);
        }

        // Add a simple posted_at timestamp column on-the-fly (if exists) to
        // guard against double-posting. If the column does not exist, we
        // quietly ignore — this keeps the logic flexible.
        if ($grn->getConnection()->getSchemaBuilder()->hasColumn($grn->getTable(), 'posted_at')) {
            $grn->forceFill(['posted_at' => now()])->saveQuietly();
        }
    }

    /**
     * Receiving goods is the one moment cost moves. Record what it moved to.
     *
     * Three separate things happen, which the old single-line version ran
     * together by overwriting products.purchase_price:
     *
     *  1. The costing ledger gains a weighted-average row. Taking the
     *     delivery's own cost outright is what let 5 units at 200 restate 50
     *     units that had cost 400.
     *  2. The vendor's purchase list records what they charged today, closing
     *     yesterday's quote rather than erasing it - so a PO raised last month
     *     still reads at last month's price.
     *  3. The sale price follows only if the product asks it to. That is the
     *     pricing_mode policy: 'cost_plus_markup' keeps the old automatic
     *     behaviour, 'manual' means receiving stock never changes what we
     *     charge for it.
     *
     * A zero or missing cost is skipped rather than written: a delivery with no
     * price on it is unpriced, not free, and writing the zero would derive a
     * zero selling price for it.
     */
    private function applyReceivedCost(\App\Models\SupplyItem $item): void
    {
        $product = $item->product;

        if (! $product || (float) $item->unit_cost <= 0) {
            return;
        }

        $unitCost = (float) $item->unit_cost;

        // Dated by when the goods arrived, not when the button was pressed, so
        // the ledger reads correctly for a receipt entered a few days late.
        $receivedAt = $item->supply?->supply_date
            ? \Illuminate\Support\Carbon::parse($item->supply->supply_date)
            : now();

        // 1. Costing ledger.
        $cost = $this->costs->recordReceipt(
            $product,
            (int) $item->quantity,
            $unitCost,
            $receivedAt,
            $item->supply?->grn,
        );

        // 2. What this vendor charged, on their own list.
        if ($vendor = $item->supply?->vendor) {
            $this->priceLists->setPrice(
                $this->priceLists->forVendor($vendor),
                $product,
                $unitCost,
            );
        }

        // 3. The sale price, if this product derives it from cost.
        if ($product->pricing_mode === 'cost_plus_markup') {
            $this->applyDerivedSellingPrice($product, (float) $cost->unit_cost);
        }

        // Legacy columns, still read across the app until the readers move off
        // them. Kept in step with the ledger rather than with the last
        // delivery, so the figure on the products page is the average the
        // stock is actually carried at.
        $product->purchase_price = round((float) $cost->unit_cost, 2);
        $product->save();
    }

    /**
     * Push the derived sale price onto the default list as a new dated row.
     *
     * Uses the averaged cost, not the delivery's, so a single cheap top-up
     * cannot mark down stock that is still mostly expensive.
     */
    private function applyDerivedSellingPrice(\App\Models\Product $product, float $averagedCost): void
    {
        $list = $this->priceLists->defaultFor(\App\Models\PriceList::TYPE_SALE);

        if (! $list || $averagedCost <= 0) {
            return;
        }

        $markup = $product->markup ?? config('pricing.default_markup', 25);

        $this->priceLists->setPrice(
            $list,
            $product,
            round($averagedCost * (1 + ($markup / 100)), 2),
        );
    }
}
