<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Pricing\PriceListService;
use App\Services\ProductPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Catalog > Product Pricing.
 *
 * Product-centric on purpose: pricing is decided one product at a time, cost
 * first and then the price it justifies, so the person setting a selling price
 * can see what the goods were bought for and what margin they are leaving.
 *
 * Every write goes through PriceListService, so changing a price closes the
 * standing row and opens a new one rather than rewriting what was charged
 * before.
 */
class ProductPricingController extends Controller
{
    public function __construct(
        private readonly ProductPricingService $service,
        private readonly PriceListService $lists,
    ) {
    }

    public function index(Request $request): View
    {
        return view('product-pricing.index', [
            'products' => $this->service->productsWithPricing(
                $request->string('search')->toString() ?: null
            ),
            'fulfilmentKinds' => ProductPricingService::FULFILMENT_KINDS,
            'unpricedCount' => $this->service->unpricedProducts()->count(),
        ]);
    }

    /** The per-product editor: costs first, then the prices they justify. */
    public function edit(int $productId): View
    {
        $product = Product::findOrFail($productId);

        return view('product-pricing.edit', $this->service->editorData($product) + [
            'assignableVendors' => $this->service->assignableVendors($product),
        ]);
    }

    /**
     * Save what each vendor charges for this product.
     *
     * Clearing a field means "no price agreed", which closes the standing quote
     * rather than writing a zero a purchase order would accept as free.
     */
    public function updatePurchasePrices(Request $request, int $productId): RedirectResponse
    {
        $product = Product::findOrFail($productId);

        $data = $request->validate([
            'vendors' => ['required', 'array'],
            'vendors.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($product, $data, $request) {
                // Re-scoped to vendors that actually carry this product, so a
                // tampered form cannot price it against somebody else.
                $carried = $product->vendors()->pluck('vendors.id')->all();

                foreach ($data['vendors'] as $vendorId => $input) {
                    if (! in_array((int) $vendorId, $carried, true)) {
                        continue;
                    }

                    $list = $this->lists->forVendor(Vendor::findOrFail($vendorId));
                    $cost = $input['unit_cost'] ?? null;

                    if ($cost === null || $cost === '') {
                        $this->lists->removePrice($list, $product);

                        continue;
                    }

                    $this->lists->setPrice($list, $product, (float) $cost, 1, null, $request->user()?->id);
                }
            });

            return back()->with('success', 'Purchase prices saved.');
        } catch (\Throwable $e) {
            \Log::error('Failed to save purchase prices for product '.$productId.': '.$e->getMessage());

            return back()->with('error', 'Unable to save those purchase prices. Please try again.');
        }
    }

    /**
     * Save what we charge, per fulfilment kind.
     *
     * A row set to follow its basis recomputes from that vendor's cost and the
     * markup; unticking pins whatever figure was typed. The basis is recorded
     * either way, because it is what makes the gross profit shown mean
     * something - it does not decide what is charged, since pooled stock has no
     * vendor identity.
     *
     * Two shortcuts sit on top of that, for the common case where the detail is
     * not wanted: a kind can be priced on one averaged figure instead of one
     * price per vendor cost, and every kind can be made to charge the same
     * thing. Both decide what gets written; neither changes how it is stored,
     * so pricing kinds apart again later needs nothing unpicked.
     */
    public function updateSalePrices(Request $request, int $productId): RedirectResponse
    {
        $product = Product::findOrFail($productId);

        $data = $request->validate([
            // One price for every fulfilment kind, rather than one each.
            'mirror' => ['nullable', 'boolean'],
            'sale' => ['required', 'array'],
            'sale.*.enabled' => ['nullable', 'boolean'],
            // 'vendor' prices against each vendor cost; 'average' prices once,
            // against the mean of them.
            'sale.*.mode' => ['nullable', 'string', 'in:vendor,average'],
            'sale.*.average.markup_percent' => ['nullable', 'numeric', 'min:0'],
            // Which basis line is the one orders charge, for this kind.
            'sale.*.charged_basis' => ['nullable', 'string'],
            'sale.*.lines' => ['nullable', 'array'],
            'sale.*.lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'sale.*.lines.*.markup_percent' => ['nullable', 'numeric', 'min:0'],
            'sale.*.lines.*.is_auto_derived' => ['nullable', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($product, $data, $request) {
                $sale = $data['sale'];

                // Charging the same everywhere: the form renders one block, and
                // whatever it says is written to every kind's list. The prices
                // stay stored per kind - this is a way of filling them in, not
                // a second place they live.
                if ($data['mirror'] ?? false) {
                    $shared = $sale[array_key_first($sale)];
                    $sale = array_fill_keys(array_keys(ProductPricingService::FULFILMENT_KINDS), $shared);
                }

                foreach ($sale as $kind => $input) {
                    if (! isset(ProductPricingService::FULFILMENT_KINDS[$kind])) {
                        continue;
                    }

                    $list = $this->service->saleListFor($kind);

                    if (! ($input['enabled'] ?? false)) {
                        $this->lists->removePrice($list, $product);

                        continue;
                    }

                    if (($input['mode'] ?? 'vendor') === 'average') {
                        $this->saveAveragedPrice(
                            $product, $list, $input['average'] ?? [], $request->user()?->id
                        );

                        continue;
                    }

                    // The form keys lines by basis id, with 'none' standing for
                    // a price that was set against no vendor cost.
                    $chargedKey = $input['charged_basis'] ?? null;
                    $saved = [];

                    foreach ($input['lines'] ?? [] as $basisKey => $line) {
                        $basis = $basisKey === 'none'
                            ? null
                            : $this->resolveBasis($product, (int) $basisKey);

                        if ($basisKey !== 'none' && ! $basis) {
                            continue; // Not one of this product's own quotes.
                        }

                        $auto = (bool) ($line['is_auto_derived'] ?? false);
                        // Null-coalesced: a line with no markup posted is a
                        // hand-typed price, not an error.
                        $markup = ($line['markup_percent'] ?? null) !== null
                            ? (float) $line['markup_percent']
                            : null;

                        // A price that follows its basis is computed here, not
                        // trusted from the form - the browser's arithmetic is a
                        // convenience, not the record.
                        $price = ($auto && $basis && $markup !== null)
                            ? round((float) $basis->unit_price * (1 + ($markup / 100)), 2)
                            : (float) ($line['unit_price'] ?? 0);

                        if ($price <= 0) {
                            continue; // Nothing entered for this vendor cost.
                        }

                        $saved[] = $this->lists->setPrice(
                            $list, $product, $price, 1, null, $request->user()?->id,
                            [
                                'markup_percent' => $markup,
                                'basis_price_list_item_id' => $basis?->id,
                                'is_auto_derived' => $auto,
                                'is_charged' => (string) $basisKey === (string) $chargedKey,
                            ]
                        );
                    }

                    // Several prices can stand at once, but exactly one is what
                    // an order pays. Without this a product could end up with
                    // prices on file and none of them chargeable.
                    if ($saved) {
                        // Pricing per vendor again after pricing on an average
                        // must retire the averaged figure, or both stand at
                        // once and which one an order pays comes down to the
                        // resolver's tie-breaks. Only that row is closed:
                        // per-vendor rows the form did not carry a price for
                        // are left alone, because a locked line posts nothing.
                        $this->lists->removeAveragedPrice($list, $product);
                        $this->lists->ensureOneCharged($list, $product);
                    }
                }
            });

            return back()->with('success', 'Selling prices saved.');
        } catch (\Throwable $e) {
            \Log::error('Failed to save selling prices for product '.$productId.': '.$e->getMessage());

            return back()->with('error', 'Unable to save those selling prices. Please try again.');
        }
    }

    /**
     * Price a fulfilment kind on one averaged figure rather than per vendor.
     *
     * Written as a single row anchored to no vendor quote, because it is not
     * derived from one: it is what the product costs on average, marked up.
     * Marked derived all the same, and that pairing - no basis, but derived -
     * is what identifies it when the editor next opens.
     *
     * The average is recomputed here rather than trusted from the form, for the
     * same reason a per-vendor derived price is: the browser's arithmetic is a
     * convenience, not the record.
     *
     * @param  array{markup_percent?: string|null}  $input
     */
    private function saveAveragedPrice(Product $product, PriceList $list, array $input, ?int $userId): void
    {
        $average = $this->service->averageCost($product);

        // No vendor has been priced yet, so there is nothing to average. The
        // editor says as much rather than offering the option, and a posted
        // form that says otherwise is not a reason to invent a price.
        if ($average === null) {
            return;
        }

        $markup = (float) ($input['markup_percent'] ?? 0);
        $price = round($average * (1 + $markup / 100), 2);

        if ($price <= 0) {
            return;
        }

        $row = $this->lists->setPrice($list, $product, $price, 1, null, $userId, [
            'markup_percent' => $markup,
            'basis_price_list_item_id' => null,
            'is_auto_derived' => true,
            'is_charged' => true,
        ]);

        // One averaged price means one price. Any per-vendor rows set before
        // the switch are closed, so exactly one figure stands for this kind.
        $this->lists->keepOnly($list, $product, [$row->id]);
    }

    /**
     * The purchase row a sale price is being reasoned from.
     *
     * Re-scoped to this product's own vendor quotes, so a posted id cannot
     * anchor a price to something unrelated.
     */
    private function resolveBasis(Product $product, ?int $basisId): ?PriceListItem
    {
        if (! $basisId) {
            return null;
        }

        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->where('price_lists.type', PriceList::TYPE_PURCHASE)
            ->where('price_list_items.product_id', $product->id)
            ->where('price_list_items.id', $basisId)
            ->select('price_list_items.*')
            ->first();
    }

    /** What one product has been priced and costed at, over time. */
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
