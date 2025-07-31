<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Retailer;
use App\Models\ProductStock;
use App\Models\StockTransaction;
use Illuminate\Database\Eloquent\Collection;

class StockLocationService
{
    /**
     * Get all locations with computed data
     */
    public function getAllLocationsWithComputedData(): Collection
    {
        // Only use warehouses and retailers tables - no stock_locations table
        $warehouses = Warehouse::all();
        $retailers = Retailer::all();

        $warehouses = $warehouses->map(fn($w) => $this->appendComputedData($w, 'warehouse'));
        $retailers = $retailers->map(fn($r) => $this->appendComputedData($r, 'retailer'));

        return $warehouses->merge($retailers);
    }

    /**
     * Get location with computed data by ID
     */
    public function getLocationWithComputedData(int $id): mixed
    {
        // Only check warehouses and retailers tables - no stock_locations table
        $location = Warehouse::find($id) ?? Retailer::find($id);
        
        if (!$location) {
            abort(404);
        }

        $locationType = match (get_class($location)) {
            Warehouse::class => 'warehouse',
            Retailer::class => 'retailer',
            default => 'other',
        };

        return $this->appendComputedData($location, $locationType);
    }

    /**
     * Append computed fields to a location model
     */
    private function appendComputedData(mixed $model, string $type): mixed
    {
        $model->location_type = $type;
        $model->status ??= 'active';
        $model->is_default = (bool) ($model->is_default ?? false);
        $model->contact_person ??= null;
        $model->contact_number ??= null;
        $model->email ??= null;

        // Stock balances & transactions
        $modelClass = get_class($model);

        $model->stockBalances = ProductStock::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->get();

        $model->stockTransactions = StockTransaction::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->latest('transaction_date')
            ->get();

        return $model;
    }

    /**
     * Get location statistics
     */
    public function getLocationStatistics(): array
    {
        $totalWarehouses = Warehouse::count();
        $totalRetailers = Retailer::count();

        return [
            'total_warehouses' => $totalWarehouses,
            'total_retailers' => $totalRetailers,
            'total_locations' => $totalWarehouses + $totalRetailers,
        ];
    }

    /**
     * Get locations by type
     */
    public function getLocationsByType(string $type): Collection
    {
        return match ($type) {
            'warehouse' => Warehouse::all(),
            'retailer' => Retailer::all(),
            default => collect(), // Return empty collection for unsupported types
        };
    }
} 