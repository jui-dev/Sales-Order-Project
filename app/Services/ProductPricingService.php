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

        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->whereIn('price_lists.code', $codes)
            ->whereIn('price_list_items.product_id', $productIds)
            ->where('price_list_items.min_quantity', 1)
            ->whereNull('price_list_items.ends_at')
            ->with('basis')
            ->select('price_list_items.*', 'price_lists.code as list_code')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->keyBy(
                fn ($row) => collect(self::FULFILMENT_KINDS)
                    ->search(fn ($kind) => $kind['code'] === $row->list_code)
            ));
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

        $saleKinds = [];
        foreach (self::FULFILMENT_KINDS as $key => $kind) {
            $row = $sale->get($key);
            $saleKinds[$key] = $kind + [
                'row' => $row,
                'unit_price' => $row ? (float) $row->unit_price : null,
                'markup_percent' => $row?->markup_percent !== null
                    ? (float) $row->markup_percent
                    : (float) ($product->markup ?? config('pricing.default_markup', 25)),
                'basis_id' => $row?->basis_price_list_item_id,
                'is_auto_derived' => (bool) ($row?->is_auto_derived ?? true),
                'gross_profit' => $row?->grossProfit(),
                // Charged on a real order, so the figure is fixed. Setting a
                // new one opens a row from today and leaves this one closed but
                // readable as what was actually charged.
                'is_locked' => $row ? $row->isInUse() : false,
                'locked_by' => $row?->usageSummary(),
            ];
        }

        return [
            'product' => $product,
            'vendors' => $vendors,
            'saleKinds' => $saleKinds,
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
