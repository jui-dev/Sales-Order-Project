<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Services\Pricing\PriceListService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads for the Product Pricing screens.
 *
 * Writes deliberately go through PriceListService instead of living here: it
 * owns the rule that a price is never updated in place, and having one way in
 * is what keeps the history trustworthy.
 */
class ProductPricingService
{
    public function __construct(private readonly PriceListService $lists)
    {
    }

    /** Every list, with how many products each currently prices. */
    public function allLists(): Collection
    {
        return PriceList::query()
            ->withCount(['items as priced_products_count' => fn ($q) => $q->whereNull('ends_at')])
            ->with('assignments.assignable')
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * The prices currently in force on one list.
     *
     * Superseded rows are deliberately excluded - this is the screen for what a
     * list charges today. The history is a per-product view.
     */
    public function currentRows(PriceList $list, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return PriceListItem::query()
            ->with('product')
            ->where('price_list_id', $list->id)
            ->whereNull('ends_at')
            ->when($search, fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")
            ))
            ->join('products', 'products.id', '=', 'price_list_items.product_id')
            ->orderBy('products.name')
            ->orderBy('price_list_items.min_quantity')
            ->select('price_list_items.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Products this list does not price yet, for the add form. */
    public function assignableProducts(PriceList $list): Collection
    {
        $priced = PriceListItem::where('price_list_id', $list->id)
            ->whereNull('ends_at')
            ->where('min_quantity', 1)
            ->pluck('product_id');

        return Product::whereNotIn('id', $priced)->orderBy('name')->get(['id', 'name', 'sku']);
    }

    /**
     * Everything one product has ever been priced at, on every list.
     *
     * The payoff of effective dating: what a price was, when it changed, and
     * what it changed to - rather than only what it is now.
     */
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

    /**
     * Products carrying no in-force price on the default sale list.
     *
     * Worth surfacing: an unpriced product falls back to cost plus markup on
     * the order form, which is a stopgap rather than an agreed price.
     */
    public function unpricedProducts(): Collection
    {
        $default = $this->lists->defaultFor(PriceList::TYPE_SALE);

        if (! $default) {
            return Product::orderBy('name')->get(['id', 'name', 'sku']);
        }

        $priced = PriceListItem::where('price_list_id', $default->id)
            ->inForceAt(Carbon::now())
            ->pluck('product_id');

        return Product::whereNotIn('id', $priced)->orderBy('name')->get(['id', 'name', 'sku']);
    }
}
