<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockLocation;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class StockManagementController extends Controller
{
    /**
     * Display the master list of stock transactions with optional filters.
     */
    public function index(Request $request)
    {
        // --------------------------------------------------------
        // Build base query
        // --------------------------------------------------------
        // Order by newest record first based on primary key to ensure stable sorting
        $query = StockTransaction::with(['product', 'location'])
            ->latest('id');

        // --------------------------------------------------------
        // Apply dynamic filters (if provided)
        // --------------------------------------------------------
        if ($request->filled('product_search')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('product_search') . '%');
            });
        }

        if ($request->filled('location_type')) {
            $query->where('location_type', $request->input('location_type') === 'warehouse' ? Warehouse::class : Retailer::class);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->input('transaction_type'));
        }

        if ($request->filled('status') && \Schema::hasColumn('stock_transactions', 'status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        // --------------------------------------------------------
        // Pagination (50 per page by default)
        // --------------------------------------------------------
        $stockTransactions = $query->paginate(50)->withQueryString();

        // ----------------------------------------------------------------
        // Enrich each transaction so required columns in Blade are filled
        // ----------------------------------------------------------------
        $stockTransactions->getCollection()->transform(function (StockTransaction $txn) {
            /* ----------------------------------------------------------
             | Source / Destination Locations
             ----------------------------------------------------------*/
            if ($txn->direction === 'outbound') {
                $txn->sourceLocation      = $txn->location; // where stock left
                $txn->destinationLocation = null;
            } else {
                $txn->sourceLocation      = null;
                $txn->destinationLocation = $txn->location; // where stock arrived
            }

            /* ----------------------------------------------------------
             | Unit & Total Costs – fallback to product purchase_price
             ----------------------------------------------------------*/
            if (empty($txn->unit_cost) || $txn->unit_cost === 0.0) {
                $txn->unit_cost = $txn->product?->purchase_price ?? 0.0;
            }
            $txn->total_cost = $txn->unit_cost * abs($txn->quantity);

            /* ----------------------------------------------------------
             | Notes & Status – use reference model if available
             ----------------------------------------------------------*/
            if (empty($txn->notes) && $txn->reference_type && $txn->reference_id) {
                try {
                    $ref = app($txn->reference_type)::find($txn->reference_id);
                    $txn->notes  = $ref->notes  ?? null;
                    $txn->status = $ref->status ?? ($txn->status ?? 'completed');
                } catch (\Throwable $e) {
                    $txn->status ??= 'completed';
                }
            }

            /* ----------------------------------------------------------
             | Reference number helper so Blade displays something even
             | if not present in DB.
             ----------------------------------------------------------*/
            $txn->reference_number = 'TXN-' . str_pad($txn->id, 6, '0', STR_PAD_LEFT);

            return $txn;
        });

        // --------------------------------------------------------
        // Build list of locations for filter dropdown
        // --------------------------------------------------------
        $stockLocations = collect()
            ->merge(Warehouse::select('id', 'name')->get()->map(function ($m) {
                $m->location_type = 'warehouse';
                return $m;
            }))
            ->merge(Retailer::select('id', 'name')->get()->map(function ($m) {
                $m->location_type = 'retailer';
                return $m;
            }))
            ->merge(StockLocation::select('id', 'name')->get()->map(function ($m) {
                $m->location_type = $m->type ?? 'other';
                return $m;
            }));

        // --------------------------------------------------------
        // Support AJAX auto-refresh (badge counts)
        // --------------------------------------------------------
        if ($request->boolean('ajax')) {
            return response()->json([
                'total' => $stockTransactions->total(),
            ]);
        }

        // --------------------------------------------------------
        // Support CSV export (simple approach)
        // --------------------------------------------------------
        if ($request->boolean('export')) {
            return $this->exportCsv($query->get());
        }

        return view('stock-management.index', compact('stockTransactions', 'stockLocations'));
    }

    /**
     * Show stock transactions for a single product.
     */
    public function productHistory(Product $product, Request $request)
    {
        $query = StockTransaction::with(['location'])
            ->where('product_id', $product->id)
            ->latest('id');

        // Optional date filters to narrow down results
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        $stockTransactions = $query->paginate(100)->withQueryString();

        // Enrich collection similar to index method
        $stockTransactions->getCollection()->transform(function (StockTransaction $txn) {
            if ($txn->direction === 'outbound') {
                $txn->sourceLocation      = $txn->location;
                $txn->destinationLocation = null;
            } else {
                $txn->sourceLocation      = null;
                $txn->destinationLocation = $txn->location;
            }

            if (empty($txn->unit_cost) || $txn->unit_cost === 0.0) {
                $txn->unit_cost = $txn->product?->purchase_price ?? 0.0;
            }
            $txn->total_cost = $txn->unit_cost * abs($txn->quantity);

            if (empty($txn->notes) && $txn->reference_type && $txn->reference_id) {
                try {
                    $ref = app($txn->reference_type)::find($txn->reference_id);
                    $txn->notes  = $ref->notes  ?? null;
                    $txn->status = $ref->status ?? ($txn->status ?? 'completed');
                } catch (\Throwable $e) {
                    $txn->status ??= 'completed';
                }
            }

            $txn->reference_number = 'TXN-' . str_pad($txn->id, 6, '0', STR_PAD_LEFT);

            return $txn;
        });

        // Re-use same view; it will automatically list only this product's records
        // Provide stockLocations collection for filter modal (re-use from index)
        $stockLocations = collect()
            ->merge(Warehouse::select('id', 'name')->get()->map(fn ($m) => tap($m, fn ($m) => $m->location_type = 'warehouse')))
            ->merge(Retailer::select('id', 'name')->get()->map(fn ($m) => tap($m, fn ($m) => $m->location_type = 'retailer')))
            ->merge(StockLocation::select('id', 'name')->get()->map(fn ($m) => tap($m, fn ($m) => $m->location_type = $m->type ?? 'other')));

        return view('stock-management.index', compact('stockTransactions', 'stockLocations', 'product'));
    }

    /**
     * Lightweight CSV export (no queue, good enough for limited rows)
     */
    protected function exportCsv($rows)
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock-transactions.csv"',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // Headings
            fputcsv($handle, [
                'ID', 'Product', 'Location', 'Type', 'Quantity', 'Direction', 'Reference', 'Date'
            ]);

            foreach ($rows as $txn) {
                fputcsv($handle, [
                    $txn->id,
                    $txn->product->name ?? '',
                    $txn->location->name ?? '',
                    $txn->transaction_type,
                    $txn->quantity,
                    $txn->direction,
                    $txn->reference_type . ':' . $txn->reference_id,
                    $txn->transaction_date,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
} 