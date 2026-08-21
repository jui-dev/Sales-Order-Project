<?php

namespace App\Services\Pricing;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Answers "what price applies here?" - and nothing else.
 *
 * Resolution is deliberately separate from snapshotting. This decides what to
 * charge at the moment of asking; the transaction is still responsible for
 * writing that number onto its own line. Re-resolving is never how a placed
 * order finds out what it was sold for.
 *
 * Precedence, highest first:
 *   1. list priority        - customer-specific 100, group 50, base 0
 *   2. assignment specificity - a list naming the customer beats one naming
 *                               their group, which beats an unassigned base list
 *   3. larger min_quantity  - the best-earned quantity break wins
 *   4. later starts_at      - the most recently agreed price
 */
class PriceResolver
{
    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    /**
     * What to charge for this product, in these circumstances.
     *
     * Falls back to cost + markup where no list prices the product, and returns
     * null when there is not even a cost to derive from - a product nobody has
     * priced and nobody has bought yet has no price, and saying so is more
     * useful than inventing a zero.
     */
    public function forSale(Product $product, ?PriceContext $context = null): ?ResolvedPrice
    {
        $context ??= new PriceContext();
        $at = $context->at();

        $item = $this->bestItem(
            $product,
            PriceList::TYPE_SALE,
            $context->assignables(),
            $context->quantity,
            $at,
        );

        if ($item) {
            return ResolvedPrice::fromItem($item);
        }

        return $this->derivedFromCost($product, $at);
    }

    /**
     * What this vendor charges for this product.
     *
     * A vendor's costs live on a purchase list assigned to them, so this is the
     * same resolution with a different type and a single assignable.
     */
    public function forPurchase(Product $product, Vendor $vendor, ?CarbonInterface $at = null): ?ResolvedPrice
    {
        $at ??= Carbon::now();

        $item = $this->bestItem($product, PriceList::TYPE_PURCHASE, [$vendor], 1, $at);

        return $item ? ResolvedPrice::fromItem($item) : null;
    }

    /**
     * Price a whole basket in one pass.
     *
     * @param  array<int, Product>  $products
     * @return array<int, ResolvedPrice|null>  keyed by product id
     */
    public function forSaleMany(array $products, ?PriceContext $context = null): array
    {
        $resolved = [];

        foreach ($products as $product) {
            $resolved[$product->id] = $this->forSale($product, $context);
        }

        return $resolved;
    }

    /**
     * The winning price row, or null if no list covers this product.
     *
     * @param  array<int, object>  $assignables  most specific first
     */
    private function bestItem(
        Product $product,
        string $type,
        array $assignables,
        int $quantity,
        CarbonInterface $at,
    ): ?PriceListItem {
        // Rank by position in $assignables so the ordering rule lives in one
        // place. Anything unassigned sits below every named match but still
        // qualifies - that is what makes a base list a fallback rather than a
        // list that never applies.
        //
        // Each entity contributes two keys: the exact one, and a class-level
        // one for assignments made with a null id ("any warehouse"). The exact
        // match outranks the class match, so a rate agreed for one store beats
        // the price every store gets.
        $rank = [];
        $step = 2;
        foreach ($assignables as $index => $assignable) {
            $base = (count($assignables) - $index) * $step;
            $rank[$assignable->getMorphClass().':'.$assignable->getKey()] = $base + 1;
            $rank[$assignable->getMorphClass().':*'] = $base;
        }

        $candidates = PriceListItem::query()
            ->with('priceList')
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->where('price_lists.type', $type)
            ->where('price_lists.is_active', true)
            ->where(fn ($q) => $q->whereNull('price_lists.starts_at')->orWhere('price_lists.starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('price_lists.ends_at')->orWhere('price_lists.ends_at', '>', $at))
            ->where('price_list_items.product_id', $product->id)
            ->where('price_list_items.min_quantity', '<=', max(1, $quantity))
            ->inForceAt($at)
            ->select('price_list_items.*')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Which lists this context is actually entitled to, and how specifically.
        $eligible = $candidates->filter(function (PriceListItem $item) use ($rank) {
            return $this->specificity($item, $rank) !== null;
        });

        if ($eligible->isEmpty()) {
            return null;
        }

        return $eligible->sortByDesc(fn (PriceListItem $item) => [
            $item->priceList->priority,
            $this->specificity($item, $rank),
            $item->min_quantity,
            $item->starts_at?->getTimestamp() ?? 0,
        ])->first();
    }

    /**
     * How closely a list matches this context, or null if it does not apply.
     *
     * 0 means an unassigned list - it applies to everyone, and so loses to any
     * list that names something about this particular buyer.
     *
     * @param  array<string, int>  $rank
     */
    private function specificity(PriceListItem $item, array $rank): ?int
    {
        $list = $item->priceList;
        $assignments = $list->assignments;

        if ($assignments->isEmpty()) {
            return 0;
        }

        $best = null;
        foreach ($assignments as $assignment) {
            // A null assignable_id means the assignment covers the whole class -
            // any warehouse, any retailer - rather than one named record.
            $key = $assignment->assignable_type.':'.($assignment->assignable_id ?? '*');

            if (isset($rank[$key])) {
                $best = max($best ?? 0, $rank[$key]);
            }
        }

        // The default list stays reachable even when its own assignment does not
        // match - it is the fallback, so an order with no fulfilment location
        // chosen yet still gets a price. It ranks below every real match.
        if ($best === null && $list->is_default) {
            return 0;
        }

        return $best;
    }

    /**
     * No list prices this product, so work one out from what it cost us.
     *
     * A stopgap that keeps the order form usable for a product nobody has
     * priced yet; ResolvedPrice::isDerived() marks it so the UI can say the
     * price still needs agreeing rather than presenting it as settled.
     */
    private function derivedFromCost(Product $product, CarbonInterface $at): ?ResolvedPrice
    {
        $cost = $this->costs->costAt($product, $at) ?? (float) ($product->purchase_price ?? 0);

        if ($cost <= 0) {
            return null;
        }

        $markup = $product->markup ?? config('pricing.default_markup', 25);

        return ResolvedPrice::derived(round($cost * (1 + ($markup / 100)), 2));
    }
}
