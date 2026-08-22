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

            // Updating the status is the whole of it: GrnObserver books the
            // stock and the ledger off that one event. They used to be split -
            // the observer posted the ledger and this method posted the stock -
            // so setting the status anywhere else booked the value of goods
            // that never reached a shelf.
            $grn->update(['status' => $toStatus]);

            return $grn;
        });
    }

    /**
     * Perform stock movements once the GRN is posted.
     *
     * We increase stock in the destination warehouse and create matching
     * stock transactions / transfer records so that the entire movement is
     * auditable.
     *
     * Driven by GrnObserver, so that the stock and the ledger move on the same
     * event rather than on two different call sites.
     */
    public function postStock(Grn $grn): void
    {
        // Posting a delivery's stock twice would double the shelves. The column
        // this reads did not exist until it was added alongside this comment,
        // so the guard had never fired.
        if ($grn->posted_at) {
            return;
        }

        $supply = $grn->supply;
        $warehouse = $supply->warehouse;

        /* ------------------------------------------------------------
         * One StockTransfer per GRN, recording the inbound movement from
         * Vendor → Warehouse.
         *
         * This used to be a firstOrCreate keyed on vendor, warehouse and date,
         * which is not unique to a delivery: two GRNs from one vendor into one
         * warehouse on one day shared a single transfer and piled their items
         * together. The posted_at guard above is what keeps this to one row per
         * GRN now.
         * ------------------------------------------------------------ */
        $transfer = \App\Models\StockTransfer::create([
            'from_location_id' => $supply->vendor_id,
            'from_location_type' => \App\Models\Vendor::class,
            'to_location_id' => $supply->warehouse_id,
            'to_location_type' => get_class($warehouse),
            'transfer_date' => $grn->received_date ?? now(),
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

                // Inside the guard, with the movement it belongs to. Sitting
                // outside it, this ran on every call, so a re-post duplicated
                // every transfer line while the stock correctly stayed put.
                $transfer->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
            }
        }

        // Mark supply as completed
        if ($supply->status !== 'completed') {
            $supply->update(['status' => 'completed']);
        }

        // What makes the guard at the top of this method mean something.
        $grn->forceFill(['posted_at' => now()])->saveQuietly();
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

        // Simple mode stops here. What the delivery cost is still a fact, and
        // the ledger above has recorded it - but the price is whatever the user
        // typed under Product Pricing, and it stays that until they change it.
        // Everything below moves a price, so none of it runs: quoting only the
        // receiving vendor would break the one-cost-for-every-vendor rule, and
        // re-deriving the selling price would move a figure nobody touched.
        if (config('pricing.simple_mode', false)) {
            return;
        }

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
