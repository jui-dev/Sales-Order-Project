<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorPickingController extends Controller
{
    public function index(Request $request): View
    {
        $query = Supply::query()->with(['vendor', 'warehouse', 'items.product', 'grn']);

        /* ---------------------------------------------------------
         | Dynamic filters driven by the UI
         ---------------------------------------------------------*/
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('vendor')) {
            $keyword = trim($request->vendor);
            $query->whereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $supplies = $query->latest('supply_date')->get();

        $supplies->each(function (Supply $supply) {
            /* ------------------------------------------------------
             | Plug missing aliases so the Blade template (originally
             | designed for PickingList) can render a Supply record
             | seamlessly.
             ------------------------------------------------------*/
            $supply->picking_number = 'SUP-' . str_pad($supply->id, 6, '0', STR_PAD_LEFT);

            // Use created_at to get an accurate time component for the
            // "Date" column while keeping the original supply date.
            $supply->picking_date = $supply->supply_date->copy()
                ->setTimeFromTimeString($supply->created_at->format('H:i:s'));

            // Transform supply items into pickingItem-like objects so the
            // quantity_requested / quantity_picked fields exist.
            $supply->pickingItems = $supply->items->map(function ($item) {
                // Clone the model instance so we don't mutate the original
                $cloned               = $item->replicate();
                $cloned->quantity_requested = $item->quantity;
                $cloned->quantity_picked    = $item->quantity; // received in full
                return $cloned;
            });

            // Destination warehouse (toLocation) – already loaded
            $supply->toLocation = $supply->warehouse;

            // Provide a self-reference so `$pickingList->supply` in Blade
            // resolves correctly.
            $supply->setRelation('supply', $supply);
        });

        // Stock transactions for vendor→warehouse (transaction_type = stock_in, reference is Supply)
        $stockTransactions = StockTransaction::with(['product', 'location'])
            ->where('transaction_type', \App\Models\StockTransaction::TYPE_STOCK_IN)
            ->where('reference_type', Supply::class)
            ->orderByDesc('transaction_date')
            ->get()
            ->each(function (StockTransaction $txn) {
                // Friendly reference number used in the table header
                $txn->reference_number = 'TXN-' . str_pad($txn->id, 6, '0', STR_PAD_LEFT);

                // Alias for quantity so Blade column shows a value
                $txn->stock_quantity = $txn->quantity;

                // Destination location (warehouse) for inbound stock
                $txn->destinationLocation = $txn->direction === 'inbound' ? $txn->location : null;

                // Default status to 'completed' if not persisted
                $txn->status = $txn->status ?? 'completed';
            });

        // Stats for hero counters
        $totalVendors      = \App\Models\Vendor::count();
        $totalWarehouses   = \App\Models\Warehouse::count();
        $recentSupplies    = Supply::latest()->take(5)->get();

        // We don't have dedicated PickingList model; reuse supplies as picking lists for the view
        $pickingLists = $supplies;

        // Dropdown data for filters
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        return view('vendor-to-warehouse-picking.index', compact(
            'supplies',
            'pickingLists',
            'stockTransactions',
            'totalVendors',
            'totalWarehouses',
            'recentSupplies',
            'warehouses',
        ));
    }
} 