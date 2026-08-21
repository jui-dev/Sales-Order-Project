<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Warehouse;
use App\Services\Pricing\PriceListService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads for the Catalog > Product Pricing screens.
 *
 * The screens are product-centric: one product, what each vendor charges for
 * it, and what we charge for it. Writes deliberately go through
 * PriceListService, which owns the rule that a price is never updated in place.
 */
class ProductPricingService
{
    /**
     * The two ways an order can be fulfilled, and the sale list each uses.
     *
     * Deliberately the location CLASS rather than individual warehouses and
     * stores: opening a new store should not mean pricing every product again.
     * Adding a third kind later is a row here plus a list.
     */
    public const FULFILMENT_KINDS = [
        'warehouse' => ['label' => 'Warehouse', 'code' => 'sale-warehouse', 'class' => Warehouse::class],
        'retailer' => ['label' => 'Retailer', 'code' => 'sale-retailer', 'class' => Retailer::class],
    ];

    public function __construct(private readonly PriceListService $lists)
    {
    }

    /**
     * Products with their purchase and sale prices, for the index table.
     */
    public function productsWithPricing(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $products = Product::query()
            ->with('category')
            ->when($search, fn ($q) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $ids = $products->pluck('id')->all();
        $purchase = $this->purchaseRowsFor($ids);
        $sale = $this->saleRowsFor($ids);

        $products->getCollection()->transform(function (Product $product) use ($purchase, $sale) {
            $product->purchase_rows = $purchase[$product->id] ?? collect();
            $product->sale_rows = $sale[$product->id] ?? collect();

            return $product;
        });

        return $products;
    }

    /**
     * What each vendor currently charges, for a set of products.
     *
     * Keyed by product id, each entry keyed by vendor id. Absent means the
     * vendor carries the product but no price has been agreed - which must stay
     * distinguishable from a price of zero.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, Collection>
     */
    public function purchaseRowsFor(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        // Vendor lists are coded vendor-{id}, so the vendor is recoverable from
        // the list without a second assignment join.
        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->where('price_lists.type', PriceList::TYPE_PURCHASE)
            ->whereIn('price_list_items.product_id', $productIds)
            ->where('price_list_items.min_quantity', 1)
            ->whereNull('price_list_items.ends_at')
            ->select('price_list_items.*', 'price_lists.name as list_name', 'price_lists.code as list_code')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->keyBy(
                fn ($row) => (int) str_replace('vendor-', '', $row->list_code)
            ));
    }

    /**
     * What we charge, per fulfilment kind, for a set of products.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, Collection>
     */
    public function saleRowsFor(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $codes = collect(self::FULFILMENT_KINDS)->pluck('code')->all();

        // Grouped by product, then by fulfilment kind - each kind may hold
        // several rows now, one per vendor cost the price was derived from.
        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->whereIn('price_lists.code', $codes)
            ->whereIn('price_list_items.product_id', $productIds)
            ->where('price_list_items.min_quantity', 1)
            ->whereNull('price_list_items.ends_at')
            ->with('basis')
            ->select('price_list_items.*', 'price_lists.code as list_code')
            ->orderByDesc('price_list_items.is_charged')
            ->orderBy('price_list_items.id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->groupBy(
                fn ($row) => collect(self::FULFILMENT_KINDS)
                    ->search(fn ($kind) => $kind['code'] === $row->list_code)
            ));
    }

    /**
     * The mean of what this product's vendors currently charge for it.
     *
     * Deliberately the costs the editor shows - the vendors recorded as
     * carrying the product, each at its standing quote - so the average can be
     * checked by eye against the rows above it. Vendors who carry the product
     * with no price agreed are left out rather than counted as zero, which
     * would drag the mean down to a figure nobody quoted.
     *
     * Null when nothing has been priced yet: there is no average of no costs,
     * and saying so beats returning a zero a selling price would be built on.
     */
    public function averageCost(Product $product): ?float
    {
        $purchase = $this->purchaseRowsFor([$product->id])->get($product->id) ?? collect();

        $costs = $product->vendors()
            ->pluck('vendors.id')
            ->map(fn ($vendorId) => $purchase->get($vendorId))
            ->filter()
            ->map(fn ($row) => (float) $row->unit_price);

        return $costs->isEmpty() ? null : round($costs->avg(), 2);
    }

    /**
     * Is every fulfilment kind currently priced identically?
     *
     * What the "same price everywhere" switch reflects when the editor opens.
     * Derived from the standing rows rather than stored as a setting on the
     * product: the prices are the record, and a flag claiming they match could
     * outlive the moment somebody priced one kind on its own.
     *
     * @param  Collection<string, Collection>  $sale  rows grouped by kind
     */
    private function kindsAreInStep(Collection $sale): bool
    {
        $fingerprints = [];

        foreach (array_keys(self::FULFILMENT_KINDS) as $key) {
            $rows = $sale->get($key) ?? collect();

            // Nothing priced for a kind is not the same as matching - there is
            // no shared price to describe.
            if ($rows->isEmpty()) {
                return false;
            }

            $fingerprints[] = $rows
                ->map(fn ($row) => implode('|', [
                    $row->basis_price_list_item_id ?? 'none',
                    (float) $row->unit_price,
                    (float) ($row->markup_percent ?? -1),
                    (int) $row->is_auto_derived,
                    (int) $row->is_charged,
                ]))
                ->sort()
                ->implode(',');
        }

        return count(array_unique($fingerprints)) === 1;
    }

    /**
     * Everything the per-product editor needs.
     *
     * Purchase first, then sale: a selling price is set against a cost, and
     * showing them the other way round invites pricing below it.
     *
     * @return array{
     *     product: Product,
     *     vendors: Collection,
     *     saleKinds: array<string, array<string, mixed>>,
     *     averageCost: float|null,
     *     mirrored: bool,
     *     stockCost: float|null
     * }
     */
    public function editorData(Product $product): array
    {
        $purchase = $this->purchaseRowsFor([$product->id])->get($product->id) ?? collect();
        $sale = $this->saleRowsFor([$product->id])->get($product->id) ?? collect();

        // Every vendor recorded as able to supply this product, priced or not.
        $vendors = $product->vendors()
            ->orderBy('name')
            ->get()
            ->map(function ($vendor) use ($purchase) {
                $row = $purchase->get($vendor->id);
                $vendor->price_row = $row;
                $vendor->current_cost = $row ? (float) $row->unit_price : null;
                // A quote already charged on a purchase order is a matter of
                // record. It can be superseded but never altered.
                $vendor->is_locked = $row ? $row->isInUse() : false;
                $vendor->locked_by = $row?->usageSummary();

                return $vendor;
            });

        $defaultMarkup = (float) ($product->markup ?? config('pricing.default_markup', 25));
        $averageCost = $this->averageCost($product);

        // One editable line per vendor cost, per fulfilment kind. A vendor with
        // an agreed cost but no selling price yet still gets a line, so the gap
        // is visible and fillable rather than hidden.
        $saleKinds = [];
        foreach (self::FULFILMENT_KINDS as $key => $kind) {
            $rows = $sale->get($key) ?? collect();
            $byBasis = $rows->keyBy('basis_price_list_item_id');

            // The averaged price is stored as a single row anchored to no
            // vendor quote, because it is not derived from one. That absent
            // basis together with is_auto_derived is what tells it apart from a
            // price typed by hand before any vendor cost existed, which is also
            // basis-less but was never derived from anything.
            $unanchored = $byBasis->get(null);
            $averageRow = $unanchored && $unanchored->is_auto_derived ? $unanchored : null;

            $lines = [];
            foreach ($vendors as $vendor) {
                if (! $vendor->price_row) {
                    continue; // Nothing to derive a price from yet.
                }

                $row = $byBasis->get($vendor->price_row->id);

                $lines[] = [
                    'basis_id' => $vendor->price_row->id,
                    'vendor_name' => $vendor->name,
                    'cost' => (float) $vendor->price_row->unit_price,
                    'row' => $row,
                    'unit_price' => $row ? (float) $row->unit_price : null,
                    'markup_percent' => $row && $row->markup_percent !== null
                        ? (float) $row->markup_percent
                        : $defaultMarkup,
                    'is_auto_derived' => (bool) ($row?->is_auto_derived ?? true),
                    'is_charged' => (bool) ($row?->is_charged ?? false),
                    'gross_profit' => $row?->grossProfit(),
                    // Charged on a real order, so the figure is fixed. Setting
                    // a new one opens a row from today and leaves this one
                    // closed but readable as what was actually charged.
                    'is_locked' => $row ? $row->isInUse() : false,
                    'locked_by' => $row?->usageSummary(),
                ];
            }

            // A price set before any vendor cost existed has no basis to sit
            // under, so it keeps a line of its own rather than disappearing.
            if ($unanchored && ! $averageRow) {
                array_unshift($lines, [
                    'basis_id' => null,
                    'vendor_name' => 'No vendor basis',
                    'cost' => null,
                    'row' => $unanchored,
                    'unit_price' => (float) $unanchored->unit_price,
                    'markup_percent' => $unanchored->markup_percent !== null
                        ? (float) $unanchored->markup_percent
                        : $defaultMarkup,
                    'is_auto_derived' => false,
                    'is_charged' => (bool) $unanchored->is_charged,
                    'gross_profit' => null,
                    'is_locked' => $unanchored->isInUse(),
                    'locked_by' => $unanchored->usageSummary(),
                ]);
            }

            $saleKinds[$key] = $kind + [
                'lines' => $lines,
                // Whether anything is priced for this kind at all, which is
                // what its on/off switch reflects. Taken from the standing rows
                // rather than from the per-vendor lines, because an averaged
                // price is a standing row that no per-vendor line carries.
                'is_priced' => $rows->isNotEmpty(),
                // Which of the two ways this kind is currently priced. Read
                // back from the rows themselves rather than remembered, so the
                // editor always opens describing what is actually in force.
                'mode' => $averageRow ? 'average' : 'vendor',
                'average' => [
                    'cost' => $averageCost,
                    'row' => $averageRow,
                    'unit_price' => $averageRow ? (float) $averageRow->unit_price : null,
                    'markup_percent' => $averageRow && $averageRow->markup_percent !== null
                        ? (float) $averageRow->markup_percent
                        : $defaultMarkup,
                    'is_locked' => $averageRow ? $averageRow->isInUse() : false,
                    'locked_by' => $averageRow?->usageSummary(),
                ],
            ];
        }

        return [
            'product' => $product,
            'vendors' => $vendors,
            'saleKinds' => $saleKinds,
            'averageCost' => $averageCost,
            // Whether every kind is priced alike right now, which is what the
            // "same price everywhere" switch opens set to.
            'mirrored' => $this->kindsAreInStep($sale),
            'stockCost' => app(\App\Services\Pricing\ProductCostService::class)->costAt($product),
        ];
    }

    /** Vendors not yet recorded as able to supply this product. */
    public function assignableVendors(Product $product): Collection
    {
        return \App\Models\Vendor::whereNotIn('id', $product->vendors()->pluck('vendors.id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Every price this product has ever carried, newest first. */
    public function historyFor(Product $product): Collection
    {
        return $this->lists->historyFor($product);
    }

    /** The cost side of the same story. */
    public function costHistoryFor(Product $product): Collection
    {
        return \App\Models\ProductCost::where('product_id', $product->id)
            ->with('source')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get();
    }

    /** The sale list backing one fulfilment kind. */
    public function saleListFor(string $kind): PriceList
    {
        $code = self::FULFILMENT_KINDS[$kind]['code']
            ?? throw new \InvalidArgumentException("Unknown fulfilment kind [{$kind}].");

        return PriceList::where('code', $code)->firstOrFail();
    }

    /**
     * Products with no sale price for any fulfilment kind.
     *
     * Worth surfacing: these fall back to cost plus markup on the order form,
     * which is a stopgap rather than a price anyone agreed.
     */
    public function unpricedProducts(): Collection
    {
        $codes = collect(self::FULFILMENT_KINDS)->pluck('code')->all();

        $priced = PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->whereIn('price_lists.code', $codes)
            ->inForceAt(Carbon::now())
            ->distinct()
            ->pluck('price_list_items.product_id');

        return Product::whereNotIn('id', $priced)->orderBy('name')->get(['id', 'name', 'sku']);
    }
}
