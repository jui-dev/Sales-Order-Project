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

                return $vendor;
            },
            'vendor',
            $id
        );
    }

    /**
     * Add a product to the vendor's price list.
     *
     * Assignment and pricing are deliberately separate: the row starts with a
     * null unit_cost so an unpriced product is visible as "needs a price"
     * rather than quietly reading as zero on a purchase order.
     */
    public function assignProduct(int $vendorId, int $productId, ?float $unitCost = null): VendorProduct
    {
        return $this->handleServiceOperation(
            function () use ($vendorId, $productId, $unitCost) {
                $vendor = $this->findOrFail(Vendor::class, $vendorId, 'vendor');

                return VendorProduct::firstOrCreate(
                    ['vendor_id' => $vendor->id, 'product_id' => $productId],
                    ['unit_cost' => $unitCost],
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

                        $row->update([
                            'unit_cost' => ($input['unit_cost'] ?? null) === null || $input['unit_cost'] === ''
                                ? null
                                : (float) $input['unit_cost'],
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