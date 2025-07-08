<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VendorController;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse;
use App\Models\Retailer;
use App\Models\StockLocation;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\VendorPickingController;
use App\Http\Controllers\PickingListController;
use App\Http\Controllers\StockManagementController;
use Illuminate\Support\Str;
use App\Http\Controllers\ReportController;
use App\Services\ProductService;
use App\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Web Routes - UI Only
|--------------------------------------------------------------------------
|
| These routes provide access to the UI views without backend functionality.
| All backend controllers and models have been removed.
|
*/

// Dashboard
Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

// Products Routes (Controller ➜ Service ➜ Model)
Route::resource('products', ProductController::class);
Route::get('products/{id}/transaction-history', [ProductController::class, 'transactionHistory'])->name('products.transaction-history');
Route::get('products/{product}/stock-analysis', function (Product $product) {
    $service   = new ProductService();
    $stockData = $service->stockAnalysis($product);

    return view('products.stock-analysis', compact('stockData'));
})->whereNumber('product')->name('products.stock-analysis');

// Customers Routes
Route::resource('customers', CustomerController::class);

// Vendors Routes
Route::resource('vendors', VendorController::class);

// Orders UI Routes
Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');

Route::get('/orders/create', function () {
    // Fetch customers list for the dropdown
    $customers = \App\Models\Customer::all();

    // Only include products that currently have stock available (>0).
    // The system maintains an `available_stocks` column on the products table
    // so we can rely on that figure to keep the query lightweight.
    $products = \App\Models\Product::query()
        ->where('available_stocks', '>', 0)
        ->get()
        ->map(function ($product) {
            // Alias to a common attribute expected by the Blade.
            $product->current_stock = $product->available_stocks;
            return $product;
        });

    // Warehouses & retailers may still be needed by the Blade for client-side checks
    $warehouses = \App\Models\Warehouse::all();
    $retailers  = \App\Models\Retailer::all();

    return view('orders.create', compact('customers', 'products', 'warehouses', 'retailers'));
})->name('orders.create');

Route::get('/orders/{id}', [\App\Http\Controllers\OrderController::class, 'show'])->whereNumber('id')->name('orders.show');

Route::get('/orders/{id}/edit', function ($id) {
    $order = \App\Models\Order::with(['orderItems.product', 'customer'])->findOrFail($id);

    // Dropdown data
    $customers  = \App\Models\Customer::orderBy('name')->get();
    $products   = \App\Models\Product::orderBy('name')->get();
    $warehouses = \App\Models\Warehouse::all();
    $retailers  = \App\Models\Retailer::all();

    return view('orders.edit', compact('order', 'customers', 'products', 'warehouses', 'retailers'));
})->whereNumber('id')->name('orders.edit');

// Supplies Routes (Controller)
Route::resource('supplies', SupplyController::class)->only(['index', 'create', 'store', 'show']);

// Custom route to mark supply completed
Route::patch('supplies/{supply}/completed', [SupplyController::class, 'completed'])->name('supplies.completed');

// GRN routes
Route::get('/grns', [GrnController::class, 'index'])->name('grns.index');
Route::get('/grns/{grn}', [GrnController::class, 'show'])->whereNumber('grn')->name('grns.show');
Route::patch('/grns/{grn}/status', [GrnController::class, 'updateStatus'])->name('grns.update-status');

// Stock Locations UI Routes
Route::get('/stock-locations', function () {
    // Build collection of all location models
    $warehouses = Warehouse::all();
    $retailers  = Retailer::all();
    $generic    = StockLocation::all();

    // ------------------------------------------------------------------
    // Helper to append computed fields expected by the Blade
    // ------------------------------------------------------------------
    $appendComputed = function ($model, string $type) {
        $model->location_type  = $type;
        $model->status        ??= 'active';
        $model->is_default     = (bool) ($model->is_default ?? false);
        $model->contact_person ??= null;
        $model->contact_number ??= null;
        $model->email          ??= null;

        // --------------------------------------------------------------
        // Stock balances & transactions
        // --------------------------------------------------------------
        $modelClass = get_class($model);

        $model->stockBalances = \App\Models\ProductStock::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->get();

        $model->stockTransactions = \App\Models\StockTransaction::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->latest('transaction_date')
            ->get();

        return $model;
    };

    $warehouses = $warehouses->map(fn($w) => $appendComputed($w, 'warehouse'));
    $retailers  = $retailers->map(fn($r) => $appendComputed($r, 'retailer'));
    $generic    = $generic->map(fn($g) => $appendComputed($g, $g->type ?? 'other'));

    $locations = $warehouses->merge($retailers)->merge($generic);

    return view('stock-locations.index', compact('locations'));
})->name('stock-locations.index');

Route::get('/stock-locations/create', function () {
    return view('stock-locations.create');
})->name('stock-locations.create');

Route::get('/stock-locations/{id}', function ($id) {
    // Attempt to resolve the location from each supported model
    $location = Warehouse::find($id) ?? Retailer::find($id) ?? StockLocation::find($id);
    if (!$location) {
        abort(404);
    }

    $locationType = match (get_class($location)) {
        Warehouse::class     => 'warehouse',
        Retailer::class      => 'retailer',
        default              => $location->type ?? 'other',
    };

    // Populate computed fields (reuse closure from index for consistency)
    $location = (function ($model, $type) {
        $model->location_type  = $type;
        $model->status        ??= 'active';
        $model->is_default     = (bool) ($model->is_default ?? false);
        $model->contact_person ??= null;
        $model->contact_number ??= null;
        $model->email          ??= null;

        $modelClass = get_class($model);
        $model->stockBalances = \App\Models\ProductStock::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->get();

        $model->stockTransactions = \App\Models\StockTransaction::with(['product'])
            ->where('location_type', $modelClass)
            ->where('location_id', $model->id)
            ->latest('transaction_date')
            ->get();

        return $model;
    })($location, $locationType);

    return view('stock-locations.show', ['location' => $location]);
})->whereNumber('id')->name('stock-locations.show');

Route::prefix('stock-locations')->name('stock-locations.')->group(function () {
    // Existing index and create are defined earlier; add missing edit/update/destroy routes to avoid route not defined errors
    Route::get('/{id}/edit', function ($id) {
        return view('stock-locations.create'); // reuse create form as placeholder
    })->whereNumber('id')->name('edit');

    // Store new location
    Route::post('/', function () {
        return back()->with('success', 'Location stored (placeholder).');
    })->name('store');

    Route::match(['put', 'patch'], '/{id}', function ($id) {
        return back()->with('success', "Location {$id} updated (placeholder).");
    })->whereNumber('id')->name('update');

    Route::delete('/{id}', function ($id) {
        return back()->with('success', "Location {$id} deleted (placeholder).");
    })->whereNumber('id')->name('destroy');
});

// Returns UI Routes
Route::get('/returns', function () {
    return view('returns.index');
})->name('returns.index');

Route::get('/returns/create', function () {
    return view('returns.index'); // using same index view as placeholder since create form not implemented
})->name('returns.create');

// Picking UI Routes
Route::get('/picking', function () {
    return view('picking.index');
})->name('picking.index');

Route::get('/picking/create', function () {
    return view('picking.create');
})->name('picking.create');

// NEW: Product-specific transaction history under picking module
Route::get('/picking/product-transaction-history/{product}', function (Product $product) {
    $service = new ProductService();
    $data    = $service->transactionHistory($product);

    return view('picking.product-transaction-history', $data);
})->name('picking.product-transaction-history');

Route::get('/transaction-flow', function () {
    // Total products
    $totalProducts = \App\Models\Product::count();

    // Total stock value (on-hand across all locations) = sum(quantity * purchase_price)
    $totalStockValue = \App\Models\ProductStock::query()
        ->join('products', 'product_stocks.product_id', '=', 'products.id')
        ->selectRaw('SUM(product_stocks.quantity * products.purchase_price) as total_value')
        ->value('total_value') ?? 0;

    // Pending movements (picking lists status pending)
    $pendingMovements = \App\Models\PickingList::where('status', 'pending')->count();

    // Active pickings (status pending OR open) for any picking list
    $activePickings = \App\Models\PickingList::whereIn('status', ['pending','open'])->count();

    $stockSummary = [
        'total_products'     => $totalProducts,
        'total_stock_value'  => round($totalStockValue, 2),
        'pending_movements'  => $pendingMovements,
        'active_pickings'    => $activePickings,
    ];

    // Recent movements: use StockTransaction last 20
    $recentMovements = \App\Models\StockTransaction::with(['product', 'stockLocation'])
        ->latest('transaction_date')
        ->limit(20)
        ->get()
        ->map(function ($txn) {
            // Determine from/to: For inbound/outbound we may not have both; attach pseudo from/to for UI
            $txn->fromLocation = $txn->direction === 'outbound' ? $txn->stockLocation : null;
            $txn->toLocation   = $txn->direction === 'inbound'  ? $txn->stockLocation : null;
            $txn->movement_type = match ($txn->transaction_type) {
                \App\Models\StockTransaction::TYPE_STOCK_IN       => 'supply_in',
                \App\Models\StockTransaction::TYPE_STOCK_TRANSFER => 'transfer',
                \App\Models\StockTransaction::TYPE_ORDER_FULFILLMENT => 'sale',
                default => 'adjustment',
            };
            return $txn;
        });

    $warehouses = \App\Models\Warehouse::all();
    $retailers  = \App\Models\Retailer::all();

    return view('picking.transaction-flow', compact('stockSummary', 'recentMovements', 'warehouses', 'retailers'));
})->name('picking.transaction-flow');

// Stock Management UI Routes
Route::get('/stock-management', [StockManagementController::class, 'index'])
    ->name('stock-management.index');

// View stock history for a single product (optional convenience route)
Route::get('/stock-management/product/{product}', [StockManagementController::class, 'productHistory'])
    ->whereNumber('product')
    ->name('stock-management.product-history');

Route::get('/stock-management/retailer', function () {
    return view('stock-management.retailer.index');
})->name('stock-management.retailer.index');

Route::get('/stock-management/picking-lists', function () {
    return view('stock-management.picking-lists.index');
})->name('stock-management.picking-lists.index');

// Stock Transfers UI Routes
Route::get('/stock-transfers/warehouse-to-retailer', function () {
    $warehouses = \App\Models\Warehouse::all();
    $retailers  = \App\Models\Retailer::all();

    // All picking lists that represent warehouse → retailer movements
    $transfers = \App\Models\PickingList::with(['fromLocation', 'toLocation', 'items', 'order'])
        ->where('from_location_type', \App\Models\Warehouse::class)
        ->where('to_location_type', \App\Models\Retailer::class)
        ->latest('picking_date')
        ->get();

    $totalWarehouses = $warehouses->count();
    $totalRetailers  = $retailers->count();
    $totalTransfers  = $transfers->count();

    $orderGeneratedTransfers = $transfers->where('reference_type', \App\Models\Order::class)->count();
    $manualTransfers        = $totalTransfers - $orderGeneratedTransfers;

    // Products that currently have any stock >0 at a warehouse
    $productsWithStock = \App\Models\ProductStock::where('location_type', \App\Models\Warehouse::class)
        ->where('quantity', '>', 0)->distinct('product_id')->count('product_id');

    // For side panel: attach total_stock attribute per warehouse/retailer
    $warehouses = $warehouses->map(function ($w) {
        $w->total_stock = \App\Models\ProductStock::where('location_type', \App\Models\Warehouse::class)
            ->where('location_id', $w->id)
            ->sum('quantity');
        return $w;
    });

    $retailers = $retailers->map(function ($r) {
        $r->total_stock = \App\Models\ProductStock::where('location_type', \App\Models\Retailer::class)
            ->where('location_id', $r->id)
            ->sum('quantity');
        return $r;
    });

    return view('stock-transfers.warehouse-to-retailer.index', compact(
        'totalWarehouses', 'totalRetailers', 'totalTransfers',
        'orderGeneratedTransfers', 'manualTransfers', 'productsWithStock',
        'transfers', 'warehouses', 'retailers'
    ));
})->name('stock-transfers.warehouse-to-retailer');

Route::get('/stock-transfers/warehouse-to-retailer/create', function () {
    // Fetch all warehouses & retailers
    $warehouses = \App\Models\Warehouse::orderBy('name')->get();
    $retailers  = \App\Models\Retailer::orderBy('name')->get();

    // Build a collection of products with their stock balances per warehouse so the
    // front-end can filter locally without additional AJAX calls.
    $products = \App\Models\Product::query()
        ->select(['id', 'name', 'sku', 'purchase_price'])
        ->orderBy('name')
        ->get()
        ->map(function ($product) {
            // Collect the stock balances for this product across all warehouses
            $warehouseStocks = \App\Models\ProductStock::where('product_id', $product->id)
                ->where('location_type', \App\Models\Warehouse::class)
                ->get()
                ->map(function ($stock) use ($product) {
                    return [
                        'warehouse_id'    => $stock->location_id,
                        // Available = on-hand minus any reserved qty (if reservation not tracked we just use quantity)
                        'available_stock' => (int) ($stock->available_quantity ?? $stock->quantity ?? 0),
                        // We currently have no per-warehouse cost column, so fall back to the product's purchase_price
                        'unit_cost'       => (float) ($product->purchase_price ?? 0),
                    ];
                });

            // Expose as a plain array so JSON encoding works nicely
            $product->warehouse_stocks = $warehouseStocks->values()->toArray();
            return $product;
        });

    return view('stock-transfers.warehouse-to-retailer.create', compact('warehouses', 'retailers', 'products'));
})->name('stock-transfers.warehouse-to-retailer.create');

Route::get('/stock-transfers/warehouse-to-retailer/pending', function () {
    $pendingTransfers = \App\Models\PickingList::with(['fromLocation','toLocation','items'])
        ->where('from_location_type', \App\Models\Warehouse::class)
        ->where('to_location_type', \App\Models\Retailer::class)
        ->where('status', 'pending')
        ->latest('created_at')
        ->get();

    return view('stock-transfers.warehouse-to-retailer.pending', compact('pendingTransfers'));
})->name('stock-transfers.warehouse-to-retailer.pending');

// Stock Transfers – Warehouse to Retailer (show)
Route::get('/stock-transfers/warehouse-to-retailer/{id}', function ($id) {
    /** @var \App\Models\PickingList|null $pickingList */
    $pickingList = \App\Models\PickingList::with(['items.product'])->find($id);

    if (!$pickingList) {
        $pickingList = new \App\Models\PickingList([
            'id'           => $id,
            'status'       => 'pending',
            'picking_date' => now(),
        ]);
        $pickingList->setRelation('items', collect());
        $pickingList->setRelation('pickingItems', collect());
    } else {
        $pickingList->setRelation('pickingItems', $pickingList->items);
    }

    // Default locations (warehouse → retailer)
    $pickingList->fromLocation = (object) ['name' => 'Warehouse', 'address' => null];
    $pickingList->toLocation   = (object) ['name' => 'Retailer',  'address' => null];

    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    return view('stock-transfers.warehouse-to-retailer.show', compact('pickingList'));
})->whereNumber('id')->name('stock-transfers.warehouse-to-retailer.show');

// Warehouse Receiving UI Routes
Route::get('/warehouse/receiving', function () {
    // Return the main receiving list view with an empty collection so the
    // Blade template can iterate safely without backend data.
    return view('warehouse.receiving.index', ['receivingLists' => collect()]);
})->name('warehouse.receiving.index');

// Additional placeholder routes referenced by the UI to prevent "Route not defined" errors.
Route::prefix('warehouse/receiving')->name('warehouse.receiving.')->group(function () {
    // Pending tasks list
    Route::get('/pending', function () {
        return view('warehouse.receiving.index', ['receivingLists' => collect()]);
    })->name('pending');

    // Completed tasks list
    Route::get('/completed', function () {
        return view('warehouse.receiving.index', ['receivingLists' => collect()]);
    })->name('completed');

    // Reporting page
    Route::get('/report', function () {
        return view('warehouse.receiving.index', ['receivingLists' => collect()]);
    })->name('report');

    // Show single receiving task
    Route::get('/{id}', function ($id) {
        return view('warehouse.receiving.index', ['receivingLists' => collect()]);
    })->whereNumber('id')->name('show');

    // Quick-receive action (PATCH)
    Route::patch('/{id}/quick-receive', function () {
        return back();
    })->whereNumber('id')->name('quick-receive');

    // Cancel action (PATCH)
    Route::patch('/{id}/cancel', function () {
        return back();
    })->whereNumber('id')->name('cancel');
});

// Reports Routes
Route::get('/reports/daily-profit', [ReportController::class, 'dailyProfit'])->name('reports.daily-profit');

// Picking Lists UI Routes
Route::get('/picking-lists', [PickingListController::class, 'index'])->name('picking-lists.index');

Route::get('/picking-lists/create', function () {
    return view('picking-lists.create');
})->name('picking-lists.create');

Route::get('/picking-lists/{id}', function ($id) {
    // Fetch the PickingList with related items; if not found, still provide a stub
    $pickingList = \App\Models\PickingList::with(['items.product'])->find($id);

    if (!$pickingList) {
        // Provide a lightweight stub so the UI can still render without fatal errors.
        $pickingList = new \App\Models\PickingList([
            'id'           => $id,
            'status'       => 'pending',
            'picking_date' => now(),
        ]);
        // Attach empty relations expected by the Blade view
        $pickingList->setRelation('items', collect());
        $pickingList->setRelation('pickingItems', collect());
    } else {
        // Ensure the alias relation is loaded for the view
        $pickingList->setRelation('pickingItems', $pickingList->items);
    }

    // Backward-compatibility: only provide placeholder objects if relations are missing
    if (! $pickingList->fromLocation) {
        $pickingList->fromLocation = (object) ['name' => 'Retailer', 'address' => null];
    }
    if (! $pickingList->toLocation) {
        $pickingList->toLocation   = (object) ['name' => 'Customer', 'address' => null];
    }

    // Provide placeholder transfer number if missing
    if (empty($pickingList->picking_number)) {
        $pickingList->picking_number = 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);
    }

    return view('picking-lists.show', compact('pickingList'));
})->whereNumber('id')->name('picking-lists.show');

// Vendor → Warehouse picking dashboard
Route::get('/vendor-to-warehouse-picking', [VendorPickingController::class, 'index'])
    ->name('vendor-to-warehouse-picking.index');

// Vendor to Warehouse Picking (header/detail pages)
Route::get('/vendor-to-warehouse-picking/{id}', function ($id) {
    $pickingList = \App\Models\PickingList::with(['items.product'])->find($id);
    if (!$pickingList) {
        $pickingList = new \App\Models\PickingList([
            'id'           => $id,
            'status'       => 'pending',
            'picking_date' => now(),
        ]);
        $pickingList->setRelation('items', collect());
    }
    $pickingList->setRelation('pickingItems', $pickingList->items ?? collect());
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    // Attach stub relationships for view
    $pickingList->supply      = (object) ['supply_number' => 'SUP-' . $pickingList->id];
    $pickingList->toLocation  = (object) ['name' => 'Warehouse', 'address' => null];

    return view('vendor-to-warehouse-picking.show', compact('pickingList'));
})->whereNumber('id')->name('vendor-to-warehouse-picking.show');

Route::get('/vendor-to-warehouse-picking/picking-list/{id}', function ($id) {
    // Reuse logic above to build $pickingList stub
    $pickingList = \App\Models\PickingList::with(['items.product'])->find($id) ?? new \App\Models\PickingList([
        'id'           => $id,
        'status'       => 'pending',
        'picking_date' => now(),
    ]);
    $pickingList->setRelation('pickingItems', $pickingList->items ?? collect());
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);
    $pickingList->toLocation = (object) ['name' => 'Warehouse', 'address' => null];

    return view('vendor-to-warehouse-picking.show-picking-list', compact('pickingList'));
})->whereNumber('id')->name('vendor-to-warehouse-picking.show-picking-list');

// Supporting JSON and action routes referenced by the view
Route::get('/vendor-to-warehouse-picking/statistics', function () {
    return response()->json([
        'total_movements'     => 0,
        'completed_today'     => 0,
        'total_items_received'=> 0,
        'active_warehouses'   => 0,
    ]);
})->name('vendor-to-warehouse-picking.statistics');

Route::post('/vendor-to-warehouse-picking/{id}/trigger', function ($id) {
    return back()->with('success', "Triggered processing for supply {$id} (placeholder).");
})->whereNumber('id')->name('vendor-to-warehouse-picking.trigger');

// Warehouse to Customer Picking UI Routes
Route::get('/warehouse-to-customer-picking', function () {
    $warehouses = \App\Models\Warehouse::all();
    $customers  = \App\Models\Customer::all();

    // Fetch picking lists destined to customers (warehouse → customer)
    $pickingLists = \App\Models\PickingList::with(['fromLocation', 'order', 'items'])
        ->where('to_location_type', \App\Models\Customer::class)
        ->latest('created_at')
        ->get();

    return view('warehouse-to-customer-picking.index', compact('warehouses', 'customers', 'pickingLists'));
})->name('warehouse-to-customer-picking.index');

// Warehouse to Customer Picking (show)
Route::get('/warehouse-to-customer-picking/{id}', function ($id) {
    $pickingList = \App\Models\PickingList::with(['items.product'])->find($id) ?? new \App\Models\PickingList([
        'id'           => $id,
        'status'       => 'pending',
        'picking_date' => now(),
    ]);
    $pickingList->setRelation('pickingItems', $pickingList->items ?? collect());
    $pickingList->fromLocation = (object) ['name' => 'Warehouse', 'address' => null];
    $pickingList->toLocation   = (object) ['name' => 'Customer',  'address' => null];
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    return view('warehouse-to-customer-picking.show', compact('pickingList'));
})->whereNumber('id')->name('warehouse-to-customer-picking.show');

// Statistics JSON endpoint for warehouse-to-customer dashboard
Route::get('/warehouse-to-customer-picking/statistics', function () {
    return response()->json([
        'total_movements'     => 0,
        'completed_today'     => 0,
        'total_items_picked'  => 0,
        'active_warehouses'   => 0,
        'active_customers'    => 0,
    ]);
})->name('warehouse-to-customer-picking.statistics');

// Retailer to Customer Picking UI Routes
Route::get('/retailer-to-customer-picking', function () {
    $retailers = \App\Models\Retailer::all();
    $customers = \App\Models\Customer::all();

    // Fetch picking lists that originate from a retailer and are destined to customers
    $pickingLists = \App\Models\PickingList::with(['fromLocation', 'order', 'items'])
        ->where('from_location_type', \App\Models\Retailer::class)
        ->where('to_location_type',   \App\Models\Customer::class)
        ->latest('created_at')
        ->get();

    return view('retailer-to-customer-picking.index', compact('retailers', 'customers', 'pickingLists'));
})->name('retailer-to-customer-picking.index');

Route::get('/retailer-to-customer-picking/create', function () {
    return view('retailer-to-customer-picking.create');
})->name('retailer-to-customer-picking.create');

// Retailer to Customer Picking (show)
Route::get('/retailer-to-customer-picking/{id}', function ($id) {
    $pickingList = \App\Models\PickingList::with([
        'items.product',
        'fromLocation',
        'toLocation',
        'order.customer',
    ])->find($id) ?? new \App\Models\PickingList([
        'id'           => $id,
        'status'       => 'pending',
        'picking_date' => now(),
    ]);
    $pickingList->setRelation('pickingItems', $pickingList->items ?? collect());
    if (! $pickingList->fromLocation) {
        $pickingList->fromLocation = (object) ['name' => 'Retailer', 'address' => null];
    }
    if (! $pickingList->toLocation) {
        $pickingList->toLocation   = (object) ['name' => 'Customer', 'address' => null];
    }
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    return view('retailer-to-customer-picking.show', compact('pickingList'));
})->whereNumber('id')->name('retailer-to-customer-picking.show');

Route::prefix('retailer-to-customer-picking')->name('retailer-to-customer-picking.')->group(function () {
    // Mark entire picking list as completed when all items picked
    Route::patch('/{pickingList}/complete', function (\App\Models\PickingList $pickingList) {
        if ($pickingList->status === 'completed') {
            return back()->with('info', 'Picking list already completed.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($pickingList) {
            // Mark every item picked fully if not already
            foreach ($pickingList->items as $item) {
                $item->update([
                    'quantity_picked' => $item->quantity_requested,
                    'status'          => 'picked',
                ]);
            }

            $pickingList->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        });

        return back()->with('success', 'Picking list marked completed.');
    })->whereNumber('pickingList')->name('complete');

    // Update single item picked quantity to full
    Route::patch('/{pickingList}/item/{item}', function (\App\Models\PickingList $pickingList, \App\Models\PickingListItem $item) {
        if ($item->picking_list_id !== $pickingList->id) {
            abort(404);
        }
        $item->update([
            'quantity_picked' => $item->quantity_requested,
            'status'          => 'picked',
        ]);

        return back()->with('success', 'Item marked as picked.');
    })->whereNumber('pickingList')->whereNumber('item')->name('update-item');
});

Route::prefix('supplies')->name('supplies.')->group(function () {
    // Store new supply
    Route::post('/', function (\App\Http\Requests\StoreSupplyRequest $request) {
        // Create the supply header first
        $supply = \App\Models\Supply::create([
            'vendor_id'    => $request->vendor_id,
            'warehouse_id' => $request->warehouse_id,
            'supply_date'  => $request->supply_date,
            'status'       => 'pending', // default status
            'notes'        => $request->notes,
        ]);

        // Create related items
        $total = 0;
        foreach ($request->input('products', []) as $item) {
            $subtotal = (float) $item['quantity'] * (float) $item['unit_cost'];
            $supply->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'unit_cost'  => $item['unit_cost'],
                'subtotal'   => $subtotal,
            ]);
            $total += $subtotal;
        }

        // Update total cost
        $supply->update(['total_cost' => $total]);

        return redirect()->route('supplies.show', $supply->id)
            ->with('success', 'Supply recorded successfully.');
    })->name('store');

    // Update existing supply (PUT/PATCH)
    Route::match(['put', 'patch'], '/{id}', function ($id) {
        return back()->with('success', "Supply {$id} updated (placeholder).");
    })->whereNumber('id')->name('update');

    // Destroy supply
    Route::delete('/{id}', function ($id) {
        return back()->with('success', "Supply {$id} deleted (placeholder).");
    })->whereNumber('id')->name('destroy');

    // Mark processing
    Route::patch('/{id}/processing', fn ($id) => back()->with('success', "Supply {$id} marked processing."))->whereNumber('id')->name('processing');

    // Mark completed
    Route::patch('/{id}/completed', fn ($id) => back()->with('success', "Supply {$id} marked completed."))->whereNumber('id')->name('completed');

    // Show completion options page is GET implemented earlier (?) but ensure process route
    Route::post('/{id}/process-completion', fn ($id) => back()->with('success', "Supply {$id} completion processed."))->whereNumber('id')->name('process-completion');

    // Generate missing picking lists
    Route::post('/generate-missing-picking-lists', fn () => back()->with('success', 'Generated missing picking lists (placeholder).'))->name('generate-missing-picking-lists');
});

// Placeholder routes referenced by order-related views
Route::prefix('orders')->name('orders.')->group(function () {
    // Store new order
    Route::post('/', [\App\Http\Controllers\OrderController::class, 'store'])->name('store');

    // Update order (not fully implemented yet, will still use placeholder)
    Route::match(['put', 'patch'], '/{id}', fn ($id) => back()->with('success', "Order {$id} updated (placeholder)."))->whereNumber('id')->name('update');

    // Destroy order (placeholder)
    Route::delete('/{id}', fn ($id) => back()->with('success', "Order {$id} deleted (placeholder)."))->whereNumber('id')->name('destroy');

    // Status update (confirm etc.)
    Route::patch('/{id}/update-status', [\App\Http\Controllers\OrderController::class, 'updateStatus'])->whereNumber('id')->name('update-status');
});

// Warehouse to Customer Picking – update status (complete / cancel etc.)
Route::patch('/warehouse-to-customer-picking/{id}/update-status', function ($id) {
    request()->validate(['status' => 'required|string']);
    $status = request('status');

    /** @var \App\Models\PickingList|null $pickingList */
    $pickingList = \App\Models\PickingList::find($id);

    if ($pickingList) {
        // When marking as completed, update child items as well so progress and data stay consistent
        if (in_array($status, ['completed', 'closed'], true)) {
            foreach ($pickingList->items as $item) {
                $item->update([
                    'quantity_picked' => $item->quantity_requested,
                    'status'          => 'picked',
                ]);
            }
        }

        $pickingList->update([
            'status'       => $status,
            'completed_at' => in_array($status, ['completed', 'closed'], true) ? now() : $pickingList->completed_at,
        ]);

        return back()->with('success', 'Picking list status updated.');
    }

    return back()->with('error', 'Picking list not found.');
})->whereNumber('id')->name('warehouse-to-customer-picking.update-status');

Route::post('/stock-transfers/warehouse-to-retailer', function () {
    $data = request()->validate([
        'from_location_id'          => 'required|exists:warehouses,id',
        'to_location_id'            => 'required|exists:retailers,id',
        'notes'                     => 'nullable|string',
        'items'                     => 'required|array|min:1',
        'items.*.product_id'        => 'required|exists:products,id',
        'items.*.quantity'          => 'required|integer|min:1',
    ]);

    $warehouse = \App\Models\Warehouse::findOrFail($data['from_location_id']);
    $retailer  = \App\Models\Retailer::findOrFail($data['to_location_id']);

    /** @var \App\Models\StockTransfer $transfer */
    /** @var \App\Models\PickingList  $pickingList */

    \Illuminate\Support\Facades\DB::transaction(function () use ($data, $warehouse, $retailer, &$transfer, &$pickingList) {
        // 1. Create Stock Transfer header (pending until picking is completed)
        $transfer = \App\Models\StockTransfer::create([
            'from_location_id'   => $warehouse->id,
            'from_location_type' => \App\Models\Warehouse::class,
            'to_location_id'     => $retailer->id,
            'to_location_type'   => \App\Models\Retailer::class,
            'status'             => 'pending',
            'transfer_date'      => now(),
            'notes'              => $data['notes'] ?? null,
        ]);

        // 2. Create Picking List (open status)
        $pickingList = \App\Models\PickingList::create([
            'reference_type'     => \App\Models\StockTransfer::class,
            'reference_id'       => $transfer->id,
            'from_location_id'   => $warehouse->id,
            'from_location_type' => \App\Models\Warehouse::class,
            'to_location_id'     => $retailer->id,
            'to_location_type'   => \App\Models\Retailer::class,
            'status'             => 'pending', // will be completed later
            'picking_date'       => now(),
        ]);

        // Generate a human-readable picking number and persist silently
        $pickingList->picking_number = 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);
        $pickingList->saveQuietly();

        $transferItemsData = [];

        foreach ($data['items'] as $idx => $itemData) {
            $product  = \App\Models\Product::findOrFail($itemData['product_id']);
            $quantity = (int) $itemData['quantity'];

            // Guard against insufficient stock at creation time
            $stockAtWarehouse = \App\Models\ProductStock::where([
                'product_id'    => $product->id,
                'location_id'   => $warehouse->id,
                'location_type' => \App\Models\Warehouse::class,
            ])->first();

            if (!$stockAtWarehouse || $stockAtWarehouse->quantity < $quantity) {
                throw new \Exception("Insufficient stock of {$product->name} at {$warehouse->name}.");
            }

            // Reserve the quantity (increase reserved_quantity) – optional placeholder
            $stockAtWarehouse->reserved_quantity = ($stockAtWarehouse->reserved_quantity ?? 0) + $quantity;
            $stockAtWarehouse->save();

            // Add to picking list items
            $pickingList->items()->create([
                'product_id'         => $product->id,
                'quantity_requested' => $quantity,
                'quantity_picked'    => 0,
                'status'             => 'pending',
            ]);

            // Prepare transfer item for later creation after picking complete
            $transferItemsData[] = [
                'product_id' => $product->id,
                'quantity'   => $quantity,
            ];
        }

        // Persist transfer items (header to align with picking list quantities)
        $transfer->items()->createMany($transferItemsData);
    });

    $redirectUrl = route('stock-transfers.warehouse-to-retailer.show', $pickingList ?? 0);

    if (request()->expectsJson() || request()->ajax()) {
        return response()->json([
            'success'      => true,
            'message'      => 'Stock transfer recorded successfully. Picking list generated.',
            'redirect_url' => $redirectUrl,
        ]);
    }

    return redirect($redirectUrl)->with('success', 'Stock transfer recorded successfully. Picking list generated.');
})->name('stock-transfers.warehouse-to-retailer.store');

// Add actions for Warehouse → Retailer picking lists
Route::prefix('stock-transfers/warehouse-to-retailer')->name('stock-transfers.warehouse-to-retailer.')->group(function () {
    // Process with per-item quantities (POST)
    Route::post('/{pickingList}/process', function (\App\Models\PickingList $pickingList) {
        request()->validate(['items' => 'required|array']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pickingList) {
            foreach (request('items', []) as $itemData) {
                $pickingItem = \App\Models\PickingListItem::find($itemData['picking_item_id'] ?? 0);
                if (! $pickingItem || $pickingItem->picking_list_id !== $pickingList->id) {
                    continue;
                }
                $qtyPicked = min((int) ($itemData['quantity_picked'] ?? 0), $pickingItem->quantity_requested);
                $pickingItem->update([
                    'quantity_picked' => $qtyPicked,
                    'status'          => 'picked',
                ]);
            }

            $pickingList->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'notes'        => request('notes'),
            ]);

            // Also mark linked transfer completed
            if ($pickingList->reference_type === \App\Models\StockTransfer::class) {
                \App\Models\StockTransfer::where('id', $pickingList->reference_id)->update(['status' => 'completed']);
            }
        });

        return back()->with('success', 'Transfer processed and marked completed.');
    })->whereNumber('pickingList')->name('process');

    // Quick-complete all quantities as requested (PATCH)
    Route::patch('/{pickingList}/quick-complete', function (\App\Models\PickingList $pickingList) {
        \Illuminate\Support\Facades\DB::transaction(function () use ($pickingList) {
            foreach ($pickingList->items as $item) {
                $item->update([
                    'quantity_picked' => $item->quantity_requested,
                    'status'          => 'picked',
                ]);
            }
            $pickingList->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            if ($pickingList->reference_type === \App\Models\StockTransfer::class) {
                \App\Models\StockTransfer::where('id', $pickingList->reference_id)->update(['status' => 'completed']);
            }
        });

        return back()->with('success', 'Transfer completed successfully.');
    })->whereNumber('pickingList')->name('quick-complete');

    // Cancel transfer (PATCH)
    Route::patch('/{pickingList}/cancel', function (\App\Models\PickingList $pickingList) {
        // Rollback any reservations
        foreach ($pickingList->items as $item) {
            $stock = \App\Models\ProductStock::where([
                'product_id'    => $item->product_id,
                'location_id'   => $pickingList->from_location_id,
                'location_type' => $pickingList->from_location_type,
            ])->first();
            if ($stock) {
                $stock->reserved_quantity = max(0, $stock->reserved_quantity - $item->quantity_requested);
                $stock->save();
            }
        }

        $pickingList->update(['status' => 'cancelled']);
        if ($pickingList->reference_type === \App\Models\StockTransfer::class) {
            \App\Models\StockTransfer::where('id', $pickingList->reference_id)->update(['status' => 'cancelled']);
        }
        return back()->with('success', 'Transfer cancelled.');
    })->whereNumber('pickingList')->name('cancel');
});

// Statistics JSON endpoint for retailer-to-customer dashboard (used by AJAX in the UI)
Route::get('/api/retailer-to-customer-picking/statistics', function () {
    $baseQuery = \App\Models\PickingList::where('from_location_type', \App\Models\Retailer::class)
        ->where('to_location_type', \App\Models\Customer::class);

    $total       = (clone $baseQuery)->count();
    $completed   = (clone $baseQuery)->where('status', 'completed')->count();
    $pending     = (clone $baseQuery)->where('status', 'pending')->count();
    $inProgress  = (clone $baseQuery)->whereIn('status', ['processing', 'in_progress'])->count();

    $totalItems = \App\Models\PickingListItem::whereHas('pickingList', function ($q) {
        $q->where('from_location_type', \App\Models\Retailer::class)
          ->where('to_location_type',   \App\Models\Customer::class);
    })->sum('quantity_picked');

    return response()->json([
        'total'        => $total,
        'completed'    => $completed,
        'pending'      => $pending,
        'in_progress'  => $inProgress,
        'total_items'  => $totalItems,
    ]);
})->name('retailer-to-customer-picking.statistics');

// Generic Picking List (show)
Route::get('/picking/{id}', function ($id) {
    /** @var \App\Models\PickingList|null $pickingList */
    $pickingList = \App\Models\PickingList::with([
        'items.product',
        'fromLocation',
        'toLocation',
        'order',
        'supply',
    ])->findOrFail($id);

    // Fallback values if relations missing so that Blade does not crash
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    return view('picking.show', compact('pickingList'));
})->whereNumber('id')->name('picking.show');

// Update single picking item quantity (generic)
Route::put('/picking/{pickingList}/item/{item}', function (\App\Models\PickingList $pickingList, \App\Models\PickingListItem $item) {
    if ($item->picking_list_id !== $pickingList->id) {
        abort(404);
    }

    request()->validate(['quantity_picked' => 'required|integer|min:0']);

    $quantity = (int) request('quantity_picked');
    $quantity = min($quantity, $item->quantity_requested); // cap

    $item->update([
        'quantity_picked' => $quantity,
        'status'          => $quantity === $item->quantity_requested ? 'picked' : 'partial',
    ]);

    return back()->with('success', 'Item quantity updated.');
})->whereNumber('pickingList')->whereNumber('item')->name('picking.update-item-quantity');

/**
 * Invoice Routes
 */
Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
