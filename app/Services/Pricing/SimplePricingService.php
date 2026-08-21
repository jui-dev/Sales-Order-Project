<?php

namespace App\Services\Pricing;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\ProductPricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pricing with the vendor dimension collapsed: one cost, one price.
 *
 * The price lists underneath still store what they always did - a purchase row
 * per vendor, a sale row per fulfilment kind. This writes the same number into
 * every one of them, so the whole system keeps reading prices exactly the way
 * it does today while the user only ever sees, and only ever sets, a single
 * figure. Nothing here is a new store of prices; it is one way of filling the
 * existing one in.
 *
 * That is what makes the mode reversible. Turning config('pricing.simple_mode')
 * off brings the per-vendor editor back with every price on file and no data to
 * unpick, because none of it was written differently.
 *
 * The markup is deliberately not a per-product setting here: simple mode means
 * one figure for the whole catalogue, and it lives in config.
 */
class SimplePricingService
{
    public function __construct(
        private readonly PriceListService $lists,
        private readonly ProductPricingService $pricing,
    ) {
    }

    /** The one markup the whole catalogue is priced at, as a percentage. */
    public function markup(): float
    {
        return (float) config('pricing.default_markup', 25);
    }

    /** What a cost sells for, once the markup is on it. */
    public function sellingPriceFor(float $cost): float
    {
        return round($cost * (1 + ($this->markup() / 100)), 2);
    }

    /* ---------------------------------------------------------------------
     | Reading
     |---------------------------------------------------------------------*/

    /**
     * The figures the simple screens show, for one product.
     *
     * @return array{cost: float|null, markup: float, selling: float|null,
     *               gross_profit: float|null, derived: float|null, in_step: bool}
     */
    public function snapshotFor(Product $product): array
    {
        return $this->snapshotMany([$product->id])->get($product->id)
            ?? $this->shape(null, null);
    }

    /**
     * The same, for a page of products, without a query per row.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, array<string, mixed>>  keyed by product id
     */
    public function snapshotMany(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $purchase = $this->pricing->purchaseRowsFor($productIds);
        $sale = $this->pricing->saleRowsFor($productIds);

        return collect($productIds)->mapWithKeys(function (int $id) use ($purchase, $sale) {
            // Every vendor row holds the same figure in this mode, so the first
            // one is the cost. Absent means no price agreed - which has to stay
            // distinguishable from a cost of zero.
            $cost = optional(($purchase->get($id) ?? collect())->first())->unit_price;

            // What an order would actually pay. Only the charged row counts: a
            // product priced per vendor before the mode was turned on can still
            // carry several, and the others are not what is charged.
            $selling = ($sale->get($id) ?? collect())
                ->flatten(1)
                ->firstWhere('is_charged', true);

            return [$id => $this->shape(
                $cost !== null ? (float) $cost : null,
                $selling ? (float) $selling->unit_price : null,
            )];
        });
    }

    /**
     * The standing cost for a product, or null if nobody has agreed one.
     *
     * Falls back to the legacy column for a product costed before the price
     * lists existed, so an old record still opens with a figure in the box.
     */
    public function costFor(Product $product): ?float
    {
        $cost = $this->snapshotFor($product)['cost'];

        if ($cost !== null) {
            return $cost;
        }

        $legacy = (float) ($product->purchase_price ?? 0);

        return $legacy > 0 ? $legacy : null;
    }

    /**
     * @return array{cost: float|null, markup: float, selling: float|null,
     *               gross_profit: float|null, derived: float|null, in_step: bool}
     */
    private function shape(?float $cost, ?float $selling): array
    {
        $derived = $cost !== null ? $this->sellingPriceFor($cost) : null;

        return [
            'cost' => $cost,
            'markup' => $this->markup(),
            'selling' => $selling,
            'gross_profit' => ($cost !== null && $selling !== null)
                ? round($selling - $cost, 2)
                : null,
            'derived' => $derived,
            // Whether what is charged is what the markup says it should be.
            // Prices set under the full editor before the mode was turned on
            // can disagree, and saying so beats showing the derived figure as
            // though it were the one orders pay.
            'in_step' => $selling !== null && $derived !== null
                && abs($selling - $derived) < 0.005,
        ];
    }

    /* ---------------------------------------------------------------------
     | Writing
     |---------------------------------------------------------------------*/

    /**
     * Set the one cost, and everything that follows from it.
     *
     * Three writes, all of them the same number said three ways:
     *
     *  1. Every vendor who carries the product is quoted at that cost, so a
     *     purchase order addressed to any of them prices its lines the same.
     *  2. Every fulfilment kind sells at that cost plus the markup, as a single
     *     charged row - keepOnly retires anything else standing, so there is
     *     exactly one price an order can pay.
     *  3. The legacy columns follow, because plenty of the app still reads
     *     them. ProductObserver derives selling_price and gross_profit from
     *     purchase_price and markup, so setting those two is enough.
     *
     * Written through PriceListService like everything else, which means a
     * price change closes the standing row and opens a new one rather than
     * rewriting what was charged before.
     */
    public function apply(Product $product, float $cost, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $cost, $userId) {
            $selling = $this->sellingPriceFor($cost);

            foreach ($product->vendors as $vendor) {
                $this->lists->setPrice(
                    $this->lists->forVendor($vendor), $product, $cost, 1, null, $userId
                );
            }

            foreach (array_keys(ProductPricingService::FULFILMENT_KINDS) as $kind) {
                $list = $this->pricing->saleListFor($kind);

                $row = $this->lists->setPrice($list, $product, $selling, 1, null, $userId, [
                    'markup_percent' => $this->markup(),
                    // Anchored to no vendor quote on purpose: this price is not
                    // derived from one particular vendor's cost, it is derived
                    // from the cost, which every vendor shares.
                    'basis_price_list_item_id' => null,
                    'is_auto_derived' => true,
                    'is_charged' => true,
                ]);

                // One price means one price. Rows left over from the full
                // editor are closed rather than left standing beside this one,
                // where which of them an order pays would come down to the
                // resolver's tie-breaks.
                $this->lists->keepOnly($list, $product, [$row->id]);
            }

            $product->forceFill([
                'purchase_price' => round($cost, 2),
                'markup' => $this->markup(),
                'auto_pricing_enabled' => true,
            ])->save();
        });
    }

    /**
     * Unprice a product, for an emptied cost box.
     *
     * Cleared means no price agreed, not free - so the rows are closed rather
     * than set to zero, which a purchase order line would happily accept.
     */
    public function clear(Product $product): void
    {
        DB::transaction(function () use ($product) {
            foreach ($product->vendors as $vendor) {
                $this->lists->removePrice($this->lists->forVendor($vendor), $product);
            }

            foreach (array_keys(ProductPricingService::FULFILMENT_KINDS) as $kind) {
                $this->lists->removePrice($this->pricing->saleListFor($kind), $product);
            }

            // The columns are NOT NULL, so "unpriced" is a zero here. Written
            // explicitly because ProductObserver skips its derivation once the
            // cost is zero, which would otherwise leave the old selling price
            // sitting on the row.
            $product->forceFill([
                'purchase_price' => 0,
                'selling_price' => 0,
                'gross_profit' => 0,
            ])->save();
        });
    }

    /**
     * Quote a newly attached vendor at what the product already costs.
     *
     * Without this, adding a vendor to a priced product would leave them with
     * no row on their list, and a purchase order to them would open with an
     * empty cost - which reads as the price having been lost.
     */
    public function backfillVendor(Product $product, Vendor $vendor): void
    {
        $cost = $this->costFor($product);

        if ($cost === null || $cost <= 0) {
            return;
        }

        $list = $this->lists->forVendor($vendor);

        if ($list->type !== PriceList::TYPE_PURCHASE) {
            return;
        }

        $this->lists->setPrice($list, $product, $cost);
    }
}
