<?php

namespace App\Services;

use App\Models\Supply;
use App\Models\StockTransaction;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class VendorPickingService
{
    /**
     * Get filtered supplies for vendor picking
     */
    /**
     * Columns that may be used for sorting. Anything else falls back to `id`.
     */
    private const SORTABLE = ['id', 'supply_date', 'total_cost', 'status', 'created_at'];

    public function getFilteredSupplies(array $filters = []): Collection
    {
        $query = Supply::query()->with(['vendor', 'warehouse', 'items.product', 'grn']);

        // Global search: supply number, vendor name, warehouse name, product name/SKU
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->whereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('items.product', fn ($p) => $p->where(
                      fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                                           ->orWhere('sku', 'like', "%{$search}%")
                  ));

                // Supply numbers render as SUP-000012, so match on the digits only
                $digits = preg_replace('/\D/', '', $search);
                if ($digits !== '') {
                    $q->orWhere('id', (int) $digits);
                }
            });
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        // Kept for links/bookmarks that still pass a vendor name
        if (!empty($filters['vendor'])) {
            $keyword = trim($filters['vendor']);
            $query->whereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('supply_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('supply_date', '<=', $filters['date_to']);
        }

        $sortField = in_array($filters['sort'] ?? null, self::SORTABLE, true)
            ? $filters['sort']
            : 'id';
        $sortDirection = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortDirection)->get();
    }

    /**
     * Enrich supply data for vendor picking display
     */
    public function enrichSupplyData(Collection $supplies): Collection
    {
        return $supplies->each(function (Supply $supply) {
            // Add picking number alias for compatibility with Blade templates
            $supply->picking_number = 'SUP-' . str_pad($supply->id, 6, '0', STR_PAD_LEFT);

            // Use created_at to get an accurate time component for the "Date" column
            $supply->picking_date = $supply->supply_date->copy()
                ->setTimeFromTimeString($supply->created_at->format('H:i:s'));

            // Add status alias for compatibility
            $supply->status = $supply->status ?? 'pending';

            // Add location aliases for compatibility
            $supply->fromLocation = $supply->vendor;
            $supply->toLocation = $supply->warehouse;

            // Add pickingItems alias for compatibility with Blade templates
            // For supplies, we use the items relationship as pickingItems
            // Also add quantity aliases for compatibility
            $supply->pickingItems = $supply->items->each(function ($item) {
                $item->quantity_requested = $item->quantity;
                $item->quantity_picked = $item->quantity; // For supplies, picked = requested
                return $item;
            });

            // Add supply alias for compatibility with Blade templates
            // Since we're using supplies as picking lists, the supply is itself
            $supply->supply = $supply;

            // Add completed_at alias for compatibility with Blade templates
            // For supplies, we use updated_at when status is completed
            $supply->completed_at = $supply->status === 'completed' ? $supply->updated_at : null;
        });
    }

    /**
     * Get stock transactions for vendor picking
     */
    public function getStockTransactions(): Collection
    {
        return StockTransaction::with(['product', 'stockLocation'])
            ->where('transaction_type', 'supply')
            ->latest('transaction_date')
            ->limit(10)
            ->get();
    }

    /**
     * Get vendor picking statistics
     */
    public function getVendorPickingStatistics(): array
    {
        $totalVendors = Vendor::count();
        $totalWarehouses = Warehouse::count();
        $recentSupplies = Supply::latest()->take(5)->get();

        return [
            'total_vendors' => $totalVendors,
            'total_warehouses' => $totalWarehouses,
            'recent_supplies' => $recentSupplies,
        ];
    }

    /**
     * Get warehouses for filtering
     */
    public function getWarehousesForFilter(): Collection
    {
        return Warehouse::orderBy('name')->get();
    }

    /**
     * Get filter options for the unified search component
     */
    public function getFilterOptions(): array
    {
        return [
            'warehouse_id' => [
                'type' => 'select',
                'label' => 'Warehouse',
                'options' => Warehouse::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            'vendor_id' => [
                'type' => 'select',
                'label' => 'Vendor',
                'options' => Vendor::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ],
            ],
            'date_from' => [
                'type' => 'date',
                'label' => 'From Date',
                'placeholder' => 'Select start date',
            ],
            'date_to' => [
                'type' => 'date',
                'label' => 'To Date',
                'placeholder' => 'Select end date',
            ],
        ];
    }

    /**
     * Get sort options for the unified search component
     */
    public function getSortOptions(): array
    {
        return [
            'id' => 'ID',
            'supply_date' => 'Supply Date',
            'total_cost' => 'Total Cost',
            'status' => 'Status',
            'created_at' => 'Created Date',
        ];
    }

    /**
     * Get supply with all related data
     */
    public function getSupplyWithDetails(int $id): Supply
    {
        return Supply::with([
            'vendor',
            'warehouse',
            'items.product',
            'grn'
        ])->findOrFail($id);
    }
}
