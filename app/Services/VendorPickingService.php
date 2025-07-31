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
    public function getFilteredSupplies(array $filters = []): Collection
    {
        $query = Supply::query()->with(['vendor', 'warehouse', 'items.product', 'grn']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['vendor'])) {
            $keyword = trim($filters['vendor']);
            $query->whereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('supply_date')->get();
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