<?php

namespace App\Http\Controllers;

use App\Services\VendorPickingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorPickingController extends Controller
{
    public function __construct(private readonly VendorPickingService $vendorPickingService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->search,
            'warehouse_id' => $request->warehouse_id,
            'vendor_id' => $request->vendor_id,
            'vendor' => $request->vendor,
            'status' => $request->status,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'sort' => $request->sort,
            'direction' => $request->direction,
        ];

        $supplies = $this->vendorPickingService->getFilteredSupplies($filters);
        $supplies = $this->vendorPickingService->enrichSupplyData($supplies);

        $stockTransactions = $this->vendorPickingService->getStockTransactions();
        $statistics = $this->vendorPickingService->getVendorPickingStatistics();
        $warehouses = $this->vendorPickingService->getWarehousesForFilter();
        $filterOptions = $this->vendorPickingService->getFilterOptions();
        $sortOptions = $this->vendorPickingService->getSortOptions();

        // We don't have dedicated PickingList model; reuse supplies as picking lists for the view
        $pickingLists = $supplies;

        // Extract additional variables needed by the view
        $vendors = \App\Models\Vendor::orderBy('name')->get();
        $totalVendors = $statistics['total_vendors'];
        $totalWarehouses = $statistics['total_warehouses'];
        $recentSupplies = $statistics['recent_supplies'];

        return view('vendor-to-warehouse-picking.index', compact(
            'supplies',
            'pickingLists',
            'stockTransactions',
            'statistics',
            'warehouses',
            'vendors',
            'filterOptions',
            'sortOptions',
            'totalVendors',
            'totalWarehouses',
            'recentSupplies',
        ));
    }
}
