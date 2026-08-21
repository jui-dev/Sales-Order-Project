<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorService
{
    use HasErrorHandling;

    public function __construct(
        private readonly \App\Services\Pricing\PriceListService $priceLists,
    ) {
    }

    public function list(): Collection
    {
        return $this->getCollectionOrEmpty(Vendor::class, 'vendors');
    }

    public function get(int $id): Vendor
    {
        return $this->handleServiceOperation(
            fn() => $this->findOrFail(Vendor::class, $id, 'vendor'),
            'vendor',
            $id
        );
    }

    public function create(array $data): Vendor
    {
        return $this->handleServiceOperation(
            fn() => Vendor::create($data),
            'vendor'
        );
    }

    public function update(int $id, array $data): Vendor
    {
        return $this->handleServiceOperation(
            function() use ($id, $data) {
                $vendor = $this->findOrFail(Vendor::class, $id, 'vendor');
                $vendor->update($data);
                return $vendor;
            },
            'vendor',
            $id
        );
    }

    public function delete(int $id): void
    {
        $this->handleServiceOperation(
            function() use ($id) {
                $vendor = $this->findOrFail(Vendor::class, $id, 'vendor');
                $vendor->delete();
            },
            'vendor',
            $id
        );
    }

    /* ---------------------------------------------------------------------
     | Price list
     |---------------------------------------------------------------------*/

    /**
     * The vendor with its price list loaded, for the detail screen.
     */
    public function getWithPriceList(int $id): Vendor
    {
        return $this->handleServiceOperation(
            function () use ($id) {
                $vendor = $this->findOrFail(Vendor::class, $id, 'vendor');
                $vendor->load(['supplies', 'vendorProducts.product']);

                // Cost lives on the vendor's purchase price list, not on the
                // pivot, so the screen resolves it per row. Loaded in one query
                // and matched up here rather than resolving per row.
                $current = $this->currentVendorCosts($vendor);

                $vendor->vendorProducts->each(function (VendorProduct $row) use ($current) {
                    $row->current_cost = $current[$row->product_id] ?? null;
                });

                return $vendor;
            },
            'vendor',
            $id
        );
    }

    /**
     * What this vendor currently charges, keyed by product id.
     *
     * A product they carry but have not priced is simply absent, which is how
     * "no price agreed" stays distinguishable from a price of zero.
     *
     * @return array<int, float>
     */
    public function currentVendorCosts(Vendor $vendor): array
    {
        $list = \App\Models\PriceList::query()
            ->where('type', \App\Models\PriceList::TYPE_PURCHASE)
            ->where('code', 'vendor-'.$vendor->id)
            ->first();

        if (! $list) {
            return [];
        }

        return \App\Models\PriceListItem::query()
            ->where('price_list_id', $list->id)
            ->where('min_quantity', 1)
            ->inForceAt(now())
            ->pluck('unit_price', 'product_id')
            ->map(fn ($cost) => (float) $cost)
            ->all();
    }

    /**
     * Add a product to the vendor's price list.
     *
     * Assignment and pricing are deliberately separate: a row with no price
     * agreed is visible as "needs a price" rather than quietly reading as zero
     * on a purchase order.
     *
     * The pivot records only that the vendor carries the product. What they
     * charge goes on their purchase price list, so a quote agreed today cannot
     * restate an order raised last month.
     */
    public function assignProduct(int $vendorId, int $productId): VendorProduct
    {
        return $this->handleServiceOperation(
            function () use ($vendorId, $productId) {
                $vendor = $this->findOrFail(Vendor::class, $vendorId, 'vendor');

                return VendorProduct::firstOrCreate(
                    ['vendor_id' => $vendor->id, 'product_id' => $productId],
                );
            },
            'vendor',
            $vendorId
        );
    }

    /**
     * Save edited costs for rows already on the vendor's price list.
     *
     * Rows are keyed by vendor_product id and filtered back to this vendor, so
     * a tampered form cannot reprice another vendor's row.
     *
     * @param  array<int, array{unit_cost?: string|null, vendor_sku?: string|null, is_active?: bool}>  $rows
     */
    public function updatePriceList(int $vendorId, array $rows): void
    {
        $this->handleServiceOperation(
            function () use ($vendorId, $rows) {
                $vendor = $this->findOrFail(Vendor::class, $vendorId, 'vendor');

                DB::transaction(function () use ($vendor, $rows) {
                    $owned = VendorProduct::where('vendor_id', $vendor->id)
                        ->whereIn('id', array_keys($rows))
                        ->get();

                    foreach ($owned as $row) {
                        $input = $rows[$row->id];

                        // Carriage only. Cost is deliberately NOT writable from
                        // this screen: it is set under Product Pricing, so there
                        // is one editable copy of a price rather than two that
                        // can drift apart.
                        $row->update([
                            'vendor_sku' => $input['vendor_sku'] ?? null,
                            'is_active' => (bool) ($input['is_active'] ?? false),
                        ]);
                    }
                });
            },
            'vendor',
            $vendorId
        );
    }

    /**
     * Drop a product from the vendor's price list.
     */
    public function removeProduct(int $vendorId, int $vendorProductId): void
    {
        $this->handleServiceOperation(
            function () use ($vendorId, $vendorProductId) {
                VendorProduct::where('vendor_id', $vendorId)
                    ->where('id', $vendorProductId)
                    ->delete();
            },
            'vendor',
            $vendorId
        );
    }
} 