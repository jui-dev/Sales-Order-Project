<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRowsRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\Pricing\PriceListService;
use App\Services\ProductPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Catalog > Product Pricing screens.
 *
 * Every write goes through PriceListService so that changing a price closes the
 * standing row and opens a new one, rather than overwriting what the business
 * charged last month.
 */
class ProductPricingController extends Controller
{
    public function __construct(
        private readonly ProductPricingService $service,
        private readonly PriceListService $lists,
    ) {
    }

    public function index(): View
    {
        return view('product-pricing.index', [
            'lists' => $this->service->allLists(),
            'unpriced' => $this->service->unpricedProducts(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $list = PriceList::with('assignments.assignable')->findOrFail($id);

        return view('product-pricing.show', [
            'list' => $list,
            'rows' => $this->service->currentRows($list, $request->string('search')->toString() ?: null),
            'assignableProducts' => $this->service->assignableProducts($list),
        ]);
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        try {
            $list = $this->lists->create($request->validated());

            return redirect()->route('product-pricing.show', $list->id)
                ->with('success', 'Price list created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Unable to create the price list. Please try again.');
        }
    }

    /**
     * Add a price for a product this list does not carry yet.
     */
    public function addPrice(Request $request, int $id): RedirectResponse
    {
        $list = PriceList::findOrFail($id);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $this->lists->setPrice(
                $list,
                Product::findOrFail($data['product_id']),
                (float) $data['unit_price'],
                (int) ($data['min_quantity'] ?? 1),
                null,
                $request->user()?->id,
            );

            return back()->with('success', 'Price added.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to add that price. Please try again.');
        }
    }

    /**
     * Apply edits to several rows at once.
     *
     * A row whose price is unchanged is left alone rather than re-dated, so
     * saving the form without touching anything does not churn the history.
     */
    public function updateRows(UpdatePriceListRowsRequest $request, int $id): RedirectResponse
    {
        $list = PriceList::findOrFail($id);

        try {
            $changed = $this->lists->bulkSet(
                $list,
                $request->rowsForService($list),
                null,
                $request->user()?->id,
            );

            return back()->with(
                'success',
                $changed === 0 ? 'No prices changed.' : "{$changed} price(s) updated."
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to update those prices. Please try again.');
        }
    }

    /**
     * Stop this list pricing a product, without losing what it charged.
     */
    public function removePrice(int $id, int $productId): RedirectResponse
    {
        $list = PriceList::findOrFail($id);

        try {
            $this->lists->removePrice($list, Product::findOrFail($productId));

            return back()->with('success', 'Price removed. Its history is still on file.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to remove that price. Please try again.');
        }
    }

    /**
     * What one product has been priced and costed at, over time.
     */
    public function history(int $productId): View
    {
        $product = Product::findOrFail($productId);

        return view('product-pricing.history', [
            'product' => $product,
            'priceHistory' => $this->service->historyFor($product),
            'costHistory' => $this->service->costHistoryFor($product),
        ]);
    }
}
