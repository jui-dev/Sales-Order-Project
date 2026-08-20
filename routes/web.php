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
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ChartOfAccountsController;

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
// Gated like every other module: the landing page and its AJAX feeds carry
// revenue, top sellers and stock levels, so being signed in is not on its own
// enough to see them.
Route::get('/', function () {
    return view('dashboard');
})->middleware('permission:dashboard.view')->name('dashboard');

// Dashboard AJAX Routes
Route::prefix('dashboard')->name('dashboard.')->middleware('permission:dashboard.view')->group(function () {
    Route::get('/stats', [App\Http\Controllers\DashboardController::class, 'getStats'])->name('stats');
    Route::get('/sales-trend', [App\Http\Controllers\DashboardController::class, 'getSalesTrend'])->name('sales-trend');
    Route::get('/top-products', [App\Http\Controllers\DashboardController::class, 'getTopProducts'])->name('top-products');
    Route::get('/stock-movement', [App\Http\Controllers\DashboardController::class, 'getStockMovement'])->name('stock-movement');
    Route::get('/returns-breakdown', [App\Http\Controllers\DashboardController::class, 'getReturnsBreakdown'])->name('returns-breakdown');
    Route::get('/low-stock-products', [App\Http\Controllers\DashboardController::class, 'getLowStockProducts'])->name('low-stock-products');
    Route::get('/recent-activity', [App\Http\Controllers\DashboardController::class, 'getRecentActivity'])->name('recent-activity');
});

// Products Routes (Controller ➜ Service ➜ Model)
Route::get('products/ajax/subcategories', [ProductController::class, 'getSubcategories'])->name('products.get-subcategories');
Route::resource('products', ProductController::class);
Route::get('products/{id}/transaction-history', [ProductController::class, 'transactionHistory'])->name('products.transaction-history');
Route::get('products/{id}/stock-analysis', [ProductController::class, 'stockAnalysis'])->name('products.stock-analysis')->whereNumber('id');
Route::patch('products/{product}/complete-supplies', function (Product $product) {
    // Complete all pending supplies for this product
    $pendingSupplies = \App\Models\Supply::whereHas('supplyItems', function($query) use ($product) {
        $query->where('product_id', $product->id);
    })->whereIn('status', ['pending', 'confirmed'])->get();

    foreach ($pendingSupplies as $supply) {
        $supply->update(['status' => 'completed']);
    }

    return redirect()->back()->with('success', 'All pending supplies have been completed.');
})->name('products.complete-supplies');

// Customers Routes
Route::resource('customers', CustomerController::class);

// Vendors Routes
Route::resource('vendors', VendorController::class);

// Vendor price list - which products a vendor carries, and at what cost.
// Registered after the resource so {vendor}/products does not collide with show.
Route::post('vendors/{vendor}/products', [VendorController::class, 'assignProduct'])
    ->whereNumber('vendor')->name('vendors.products.assign');
Route::put('vendors/{vendor}/price-list', [VendorController::class, 'updatePriceList'])
    ->whereNumber('vendor')->name('vendors.price-list.update');
Route::delete('vendors/{vendor}/products/{vendorProduct}', [VendorController::class, 'removeProduct'])
    ->whereNumber('vendor')->whereNumber('vendorProduct')->name('vendors.products.remove');

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

// Additional orders routes for update, destroy, update-status
Route::put('/orders/{id}', [\App\Http\Controllers\OrderController::class, 'update'])->whereNumber('id')->name('orders.update');
Route::delete('/orders/{id}', [\App\Http\Controllers\OrderController::class, 'destroy'])->whereNumber('id')->name('orders.destroy');
// Note: update-status route is defined in the orders prefix group below

// Supplies Routes (Controller)
Route::resource('supplies', SupplyController::class)->only(['index', 'create', 'store', 'show']);

// Custom route to mark supply completed
Route::patch('supplies/{supply}/completed', [SupplyController::class, 'completed'])->name('supplies.completed');

// Confirm a pending supply
Route::patch('supplies/{supply}/confirm', [SupplyController::class, 'confirm'])->name('supplies.confirm');

// Additional supplies routes for edit, update, destroy
Route::get('supplies/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
Route::put('supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
Route::delete('supplies/{supply}', [SupplyController::class, 'destroy'])->name('supplies.destroy');

// GRN routes
Route::get('/grns', [GrnController::class, 'index'])->name('grns.index');
Route::get('/grns/{grn}', [GrnController::class, 'show'])->whereNumber('grn')->name('grns.show');
Route::patch('/grns/{grn}/status', [GrnController::class, 'updateStatus'])->name('grns.update-status');

// Additional GRN routes for edit and destroy
Route::get('/grns/{grn}/edit', [GrnController::class, 'edit'])->whereNumber('grn')->name('grns.edit');
Route::delete('/grns/{grn}', [GrnController::class, 'destroy'])->whereNumber('grn')->name('grns.destroy');

// Stock Locations UI Routes
Route::resource('stock-locations', \App\Http\Controllers\StockLocationController::class);

// AJAX routes for return functionality (must be defined before resource route)
Route::prefix('returns/ajax')->group(function () {
    Route::get('customer-invoices/{customer}', [\App\Http\Controllers\ReturnController::class, 'getCustomerInvoices']);
    Route::get('vendor-supplier-bills/{vendor}', [\App\Http\Controllers\ReturnController::class, 'getVendorSupplierBills']);
    Route::get('retailer-stock-transfers/{retailer}', [\App\Http\Controllers\ReturnController::class, 'getRetailerStockTransfers']);
    Route::get('invoice-items/{invoice}', [\App\Http\Controllers\ReturnController::class, 'getInvoiceItems']);
    Route::get('supplier-bill-items/{supplierBill}', [\App\Http\Controllers\ReturnController::class, 'getSupplierBillItems']);
    Route::get('stock-transfer-items/{stockTransfer}', [\App\Http\Controllers\ReturnController::class, 'getStockTransferItems']);
    Route::get('invoice-fulfillment-location/{invoiceId}', [\App\Http\Controllers\ReturnController::class, 'getInvoiceFulfillmentLocation'])->where('invoiceId', '[0-9]+');
    Route::get('product-return-destination/{referenceId}/{productId}', [\App\Http\Controllers\ReturnController::class, 'getProductReturnDestination'])->where(['referenceId' => '[0-9]+', 'productId' => '[0-9]+']);
    Route::post('validate-quantity', [\App\Http\Controllers\ReturnController::class, 'validateReturnQuantity']);
});

// Debug route for testing
Route::get('debug/invoice/{invoice}', function(\App\Models\Invoice $invoice) {
    try {
        $invoice->load(['items.product']);
        return response()->json([
            'invoice_id' => $invoice->id,
            'items_count' => $invoice->items->count(),
            'items' => $invoice->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product ? $item->product->name : 'Unknown',
                    'quantity' => $item->quantity,
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Test route for fulfillment location
Route::get('debug/fulfillment-location/{invoice}', function(\App\Models\Invoice $invoice) {
    try {
        $invoice->load(['order.fulfillmentLocation']);

        if (!$invoice->order) {
            return response()->json(['error' => 'No order found for this invoice'], 404);
        }

        $order = $invoice->order;
        $fulfillmentLocationId = $order->fulfillment_location_id;
        $fulfillmentLocationType = $order->fulfillment_location_type;

        // Try to load the fulfillment location manually
        if ($fulfillmentLocationType === 'App\\Models\\Warehouse') {
            $fulfillmentLocation = \App\Models\Warehouse::find($fulfillmentLocationId);
        } elseif ($fulfillmentLocationType === 'App\\Models\\Retailer') {
            $fulfillmentLocation = \App\Models\Retailer::find($fulfillmentLocationId);
        } else {
            $fulfillmentLocation = null;
        }

        return response()->json([
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'fulfillment_location_id' => $fulfillmentLocationId,
            'fulfillment_location_type' => $fulfillmentLocationType,
            'fulfillment_location' => $fulfillmentLocation ? [
                'id' => $fulfillmentLocation->id,
                'name' => $fulfillmentLocation->name,
                'type' => get_class($fulfillmentLocation),
                'type_name' => class_basename($fulfillmentLocation)
            ] : null
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Returns Routes (Unified Stock Transactions) - Specific routes must come before resource route
Route::get('returns/create-with-data', [\App\Http\Controllers\ReturnController::class, 'createWithData'])->name('returns.create-with-data');
Route::resource('returns', \App\Http\Controllers\ReturnController::class);
Route::post('returns/{return}/approve', [\App\Http\Controllers\ReturnController::class, 'approve'])->name('returns.approve');
Route::post('returns/{return}/reject', [\App\Http\Controllers\ReturnController::class, 'reject'])->name('returns.reject');
Route::post('returns/{return}/complete', [\App\Http\Controllers\ReturnController::class, 'complete'])->name('returns.complete');

// Internal Return Notes Routes - REMOVED (no longer needed)

// Credit Notes Routes
Route::get('credit-notes', [\App\Http\Controllers\CreditNoteController::class, 'index'])->name('credit-notes.index');
Route::get('credit-notes/{creditNote}', [\App\Http\Controllers\CreditNoteController::class, 'show'])->name('credit-notes.show');
Route::post('returns/{return}/generate-credit-note', [\App\Http\Controllers\CreditNoteController::class, 'generateForReturn'])->name('credit-notes.generate-for-return');
Route::post('credit-notes/{creditNote}/cancel', [\App\Http\Controllers\CreditNoteController::class, 'cancel'])->name('credit-notes.cancel');
Route::get('credit-notes/{creditNote}/download', [\App\Http\Controllers\CreditNoteController::class, 'download'])->name('credit-notes.download');
Route::post('credit-notes/{creditNote}/post', [\App\Http\Controllers\CreditNoteController::class, 'post'])->name('credit-notes.post');
Route::post('credit-notes/{creditNote}/approve-journal-entry', [\App\Http\Controllers\CreditNoteController::class, 'approveJournalEntry'])->name('credit-notes.approve-journal-entry');
Route::post('credit-notes/{creditNote}/post-journal-entry', [\App\Http\Controllers\CreditNoteController::class, 'postJournalEntry'])->name('credit-notes.post-journal-entry');

// Debit Notes Routes
Route::get('debit-notes', [\App\Http\Controllers\DebitNoteController::class, 'index'])->name('debit-notes.index');
Route::get('debit-notes/{debitNote}', [\App\Http\Controllers\DebitNoteController::class, 'show'])->name('debit-notes.show');
Route::post('returns/{return}/generate-debit-note', [\App\Http\Controllers\DebitNoteController::class, 'generateForReturn'])->name('debit-notes.generate-for-return');
Route::post('debit-notes/{debitNote}/cancel', [\App\Http\Controllers\DebitNoteController::class, 'cancel'])->name('debit-notes.cancel');
Route::get('debit-notes/{debitNote}/download', [\App\Http\Controllers\DebitNoteController::class, 'download'])->name('debit-notes.download');
Route::post('debit-notes/{debitNote}/post', [\App\Http\Controllers\DebitNoteController::class, 'post'])->name('debit-notes.post');
Route::post('debit-notes/{debitNote}/approve-journal-entry', [\App\Http\Controllers\DebitNoteController::class, 'approveJournalEntry'])->name('debit-notes.approve-journal-entry');
Route::post('debit-notes/{debitNote}/post-journal-entry', [\App\Http\Controllers\DebitNoteController::class, 'postJournalEntry'])->name('debit-notes.post-journal-entry');

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

    // Extract variables for the view - the service returns an array with keys
    return view('picking.product-transaction-history', [
        'product' => $data['product'],
        'totalSupplied' => $data['totalSupplied'],
        'totalSold' => $data['totalSold'],
        'totalTransferred' => $data['totalTransferred'],
        'currentTotalStock' => $data['currentTotalStock'],
        'currentAvailableStock' => $data['currentAvailableStock'],
        'currentReservedStock' => $data['currentReservedStock'],
        'stockBalances' => $data['stockBalances'],
        'movements' => $data['movements'],
        'pickingLists' => $data['pickingLists'],
    ]);
})->name('picking.product-transaction-history');

Route::get('/transaction-flow', [\App\Http\Controllers\TransactionFlowController::class, 'index'])->name('picking.transaction-flow');

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
                        'available_stock' => (int) (($stock->quantity ?? 0) - ($stock->reserved_quantity ?? 0)),
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
    // Confirmed transfers are the ones still awaiting completion.
    $pendingTransfers = \App\Models\PickingList::with(['fromLocation','toLocation','items'])
        ->where('from_location_type', \App\Models\Warehouse::class)
        ->where('to_location_type', \App\Models\Retailer::class)
        ->where('status', 'confirmed')
        ->latest('created_at')
        ->get();

    return view('stock-transfers.warehouse-to-retailer.pending', compact('pendingTransfers'));
})->name('stock-transfers.warehouse-to-retailer.pending');

// Stock Transfers – Warehouse to Retailer (show)
Route::get('/stock-transfers/warehouse-to-retailer/{id}', function ($id) {
    // First try to find the StockTransfer
    $stockTransfer = \App\Models\StockTransfer::with(['items.product', 'fromLocation', 'toLocation'])->find($id);

    if (!$stockTransfer) {
        // Return a 404 error if StockTransfer not found
        abort(404, 'Warehouse-to-retailer transfer not found');
    }

    // Find the associated PickingList
    $pickingList = \App\Models\PickingList::with(['items.product', 'fromLocation', 'toLocation'])
        ->where('reference_type', \App\Models\StockTransfer::class)
        ->where('reference_id', $stockTransfer->id)
        ->first();

    if (!$pickingList) {
        // If no PickingList found, create a fallback using StockTransfer data
        $pickingList = new \App\Models\PickingList([
            'id' => $stockTransfer->id,
            'status' => $stockTransfer->status,
            'from_location_id' => $stockTransfer->from_location_id,
            'from_location_type' => $stockTransfer->from_location_type,
            'to_location_id' => $stockTransfer->to_location_id,
            'to_location_type' => $stockTransfer->to_location_type,
            'picking_date' => $stockTransfer->transfer_date,
            'notes' => $stockTransfer->notes,
        ]);

        // Set the items from StockTransfer
        $pickingList->setRelation('items', $stockTransfer->items);
        $pickingList->setRelation('pickingItems', $stockTransfer->items);

        // Set the locations
        $pickingList->setRelation('fromLocation', $stockTransfer->fromLocation);
        $pickingList->setRelation('toLocation', $stockTransfer->toLocation);
    } else {
        // Ensure the pickingItems relation is set for the view
        $pickingList->setRelation('pickingItems', $pickingList->items);
    }

    // Ensure picking number is set
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    // The view needs the transfer too: transfer_date and notes live on
    // stock_transfers, and picking_lists has no notes column at all.
    return view('stock-transfers.warehouse-to-retailer.show', compact('pickingList', 'stockTransfer'));
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
Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');

// NEW: Income Statement report
Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
Route::get('/reports/cash-flow', [ReportController::class, 'cashFlowStatement'])->name('reports.cash-flow');

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

// Action route referenced by the view
Route::post('/vendor-to-warehouse-picking/{id}/trigger', function ($id) {
    return back()->with('success', "Triggered processing for supply {$id} (placeholder).");
})->whereNumber('id')->name('vendor-to-warehouse-picking.trigger');

// Warehouse to Customer Picking UI Routes
Route::get('/warehouse-to-customer-picking', function () {
    $warehouses = \App\Models\Warehouse::all();
    $customers  = \App\Models\Customer::all();

    // Warehouse-sourced picking lists destined to customers. Both sides are
    // filtered: an order fulfilled from a retailer raises a Retailer → Customer
    // list, which belongs on the retailer index, not this one.
    $pickingLists = \App\Models\PickingList::with(['fromLocation', 'order', 'items'])
        ->where('from_location_type', \App\Models\Warehouse::class)
        ->where('to_location_type', \App\Models\Customer::class)
        ->latest('created_at')
        ->get();

    return view('warehouse-to-customer-picking.index', compact('warehouses', 'customers', 'pickingLists'));
})->name('warehouse-to-customer-picking.index');

// Customer-bound picking (show). Shared by both sources: an order is picked from
// wherever it is fulfilled, so the page reads the source off the list rather than
// assuming a warehouse. The two index pages both link here.
Route::get('/customer-picking/{id}', function ($id) {
    $pickingList = \App\Models\PickingList::with([
        'items.product',
        'fromLocation.productStocks',
        'toLocation',
        'order.customer',
        'order.orderItems.product'
    ])->findOrFail($id);

    // Set up picking number if not set
    $pickingList->picking_number ??= 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);

    // Create alias relation for backward compatibility with the view
    $pickingList->setRelation('pickingItems', $pickingList->items);

    return view('customer-picking.show', compact('pickingList'));
})->whereNumber('id')->name('customer-picking.show');

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

// Retailer to Customer Picking (show). Retailer-sourced customer picking now uses
// the shared customer-picking page, so this only keeps old links working.
Route::get('/retailer-to-customer-picking/{id}', function ($id) {
    return redirect()->route('customer-picking.show', $id);
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

// Customer-bound picking – update status (complete / cancel etc.). Shared by
// warehouse- and retailer-sourced lists; PickingListObserver reads the source off
// the list, so the stock leaves the right place either way.
Route::patch('/customer-picking/{id}/update-status', function ($id) {
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

        if (in_array($status, ['completed', 'closed'], true)) {
            // Check if this picking list is associated with an order and has an invoice
            if ($pickingList->reference_type === \App\Models\Order::class) {
                $order = \App\Models\Order::find($pickingList->reference_id);
                if ($order && $order->invoice) {
                    return redirect()->route('invoices.show', $order->invoice->id)
                        ->with('success', 'Picking completed! Invoice has been generated automatically. You can now process the payment.');
                }
            }
            return back()->with('success', 'Picking completed! Invoice has been generated automatically. You can now process the payment.');
        }

        return back()->with('success', 'Picking list status updated.');
    }

    return back()->with('error', 'Picking list not found.');
})->whereNumber('id')->name('customer-picking.update-status');



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

    try {
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $warehouse, $retailer, &$transfer, &$pickingList) {
            // 1. Create Stock Transfer header (pending until picking is completed)
            $transfer = \App\Models\StockTransfer::create([
                'from_location_id'   => $warehouse->id,
                'from_location_type' => \App\Models\Warehouse::class,
                'to_location_id'     => $retailer->id,
                'to_location_type'   => \App\Models\Retailer::class,
                'status'             => 'confirmed',
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
                'status'             => 'confirmed', // stock reserved; moves on completion
                'picking_date'       => now(),
            ]);

            // Generate a human-readable picking number and persist silently
            $pickingList->picking_number = 'PL-' . str_pad($pickingList->id, 6, '0', STR_PAD_LEFT);
            $pickingList->saveQuietly();

            $transferItemsData = [];

            foreach ($data['items'] as $idx => $itemData) {
                $product  = \App\Models\Product::findOrFail($itemData['product_id']);
                $quantity = (int) $itemData['quantity'];

                // Check if warehouse has sufficient stock for the transfer
                $stockAtWarehouse = \App\Models\ProductStock::where([
                    'product_id'    => $product->id,
                    'location_id'   => $warehouse->id,
                    'location_type' => \App\Models\Warehouse::class,
                ])->first();

                if (!$stockAtWarehouse) {
                    // Create a stock record with 0 quantity - stock will be added when supplies are received
                    $stockAtWarehouse = \App\Models\ProductStock::create([
                        'product_id'    => $product->id,
                        'location_id'   => $warehouse->id,
                        'location_type' => \App\Models\Warehouse::class,
                        'quantity'      => 0,
                        'reserved_quantity' => 0,
                    ]);
                }

                // Reserve the quantity for this transfer
                $stockAtWarehouse->reserved_quantity = ($stockAtWarehouse->reserved_quantity ?? 0) + $quantity;
                $stockAtWarehouse->save();

                // Add to picking list items
                $pickingList->items()->create([
                    'product_id'         => $product->id,
                    'quantity_requested' => $quantity,
                    'quantity_picked'    => 0,
                    'status'             => 'confirmed',
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
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Error creating warehouse-to-retailer transfer', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating transfer: ' . $e->getMessage(),
            ], 500);
        }

        return redirect()->route('stock-transfers.warehouse-to-retailer.create')
            ->with('error', 'Error creating transfer: ' . $e->getMessage());
    }

    // Ensure picking list was created successfully
    if (!$pickingList) {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create picking list. Please try again.',
            ], 500);
        }

        return redirect()->route('stock-transfers.warehouse-to-retailer.create')
            ->with('error', 'Failed to create picking list. Please try again.');
    }



    // The show route resolves its {id} as a StockTransfer, so redirect with the
    // transfer id - picking_lists is an independent auto-increment sequence.
    $redirectUrl = route('stock-transfers.warehouse-to-retailer.show', $transfer->id);

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
    Route::post('/{pickingList}/process', function (\App\Models\PickingList $pickingList, \App\Services\StockTransferService $transfers) {
        request()->validate(['items' => 'required|array']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pickingList, $transfers) {
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

            // Books the stock movement, releases the reservation and marks both
            // the picking list and its transfer completed.
            $transfers->complete($pickingList->load('items'));
        });

        return back()->with('success', 'Transfer processed and marked completed.');
    })->whereNumber('pickingList')->name('process');

    // Quick-complete all quantities as requested (PATCH)
    Route::patch('/{pickingList}/quick-complete', function (\App\Models\PickingList $pickingList, \App\Services\StockTransferService $transfers) {
        \Illuminate\Support\Facades\DB::transaction(function () use ($pickingList, $transfers) {
            foreach ($pickingList->items as $item) {
                $item->update([
                    'quantity_picked' => $item->quantity_requested,
                    'status'          => 'picked',
                ]);
            }

            $transfers->complete($pickingList->load('items'));
        });

        return back()->with('success', 'Transfer completed successfully.');
    })->whereNumber('pickingList')->name('quick-complete');

    // Cancel transfer (PATCH)
    Route::patch('/{pickingList}/cancel', function (\App\Models\PickingList $pickingList, \App\Services\StockTransferService $transfers) {
        $transfers->cancel($pickingList->load('items'));

        return back()->with('success', 'Transfer cancelled.');
    })->whereNumber('pickingList')->name('cancel');
});

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
        // 'short' is what the status enum calls an incomplete line; 'partial' is
        // not one of its values and the write was rejected by the database.
        'status'          => $quantity === $item->quantity_requested ? 'picked' : 'short',
    ]);

    return back()->with('success', 'Item quantity updated.');
})->whereNumber('pickingList')->whereNumber('item')->name('picking.update-item-quantity');

/**
 * Invoice Routes
 */
Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\PaymentController::class, 'store'])->name('invoices.pay');

/**
 * Customer Payment Routes
 *
 * The sales-side counterpart of supplier-bill-payments: payments are recorded
 * against an invoice (invoices.pay above) and browsed here.
 */
Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index'])->name('payments.index');
Route::get('/payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'show'])
    ->whereNumber('payment')->name('payments.show');

// Additional invoice routes for edit, update, destroy
Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
// Manual Journal Entry creation
Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create');
Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');
Route::patch('/journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])->name('journal-entries.approve');
Route::patch('/journal-entries/{journalEntry}/reject', [JournalEntryController::class, 'reject'])->name('journal-entries.reject');
Route::patch('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
Route::get('/journal-entries/{journalEntry}/edit', [JournalEntryController::class, 'edit'])->name('journal-entries.edit');
Route::patch('/journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])->name('journal-entries.update');
Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
Route::get('/accounting/chart-of-accounts', [\App\Http\Controllers\ChartOfAccountsController::class, 'index'])->name('accounting.chart-of-accounts');
Route::get('/accounting/chart-of-accounts/create', [\App\Http\Controllers\ChartOfAccountsController::class, 'create'])->name('accounting.chart-of-accounts.create');
Route::post('/accounting/chart-of-accounts', [\App\Http\Controllers\ChartOfAccountsController::class, 'store'])->name('accounting.chart-of-accounts.store');

/**
 * Supplier Bills Routes
 */
Route::get('/supplier-bills', [\App\Http\Controllers\SupplierBillController::class, 'index'])->name('supplier-bills.index');
Route::get('/supplier-bills/{supplierBill}', [\App\Http\Controllers\SupplierBillController::class, 'show'])->name('supplier-bills.show');
Route::post('/supplier-bills/{supplierBill}/post', [\App\Http\Controllers\SupplierBillController::class, 'post'])->name('supplier-bills.post');
Route::post('/supplier-bills/{supplierBill}/pay', [\App\Http\Controllers\SupplierBillController::class, 'pay'])->name('supplier-bills.pay');
Route::get('/supplier-bills/{supplierBill}/payment-info', [\App\Http\Controllers\SupplierBillController::class, 'paymentInfo'])->name('supplier-bills.payment-info');

// Additional supplier bills routes for edit, update, destroy
Route::get('/supplier-bills/{supplierBill}/edit', [\App\Http\Controllers\SupplierBillController::class, 'edit'])->name('supplier-bills.edit');
Route::put('/supplier-bills/{supplierBill}', [\App\Http\Controllers\SupplierBillController::class, 'update'])->name('supplier-bills.update');
Route::delete('/supplier-bills/{supplierBill}', [\App\Http\Controllers\SupplierBillController::class, 'destroy'])->name('supplier-bills.destroy');

/**
 * Supplier Bill Payments Routes
 */
Route::get('/supplier-bill-payments', [\App\Http\Controllers\SupplierBillPaymentController::class, 'index'])->name('supplier-bill-payments.index');
Route::get('/supplier-bill-payments/{supplierBillPayment}', [\App\Http\Controllers\SupplierBillPaymentController::class, 'show'])->name('supplier-bill-payments.show');

/**
 * User Management Routes
 *
 * Reading the list is separated from changing it so a future read-only
 * supervisor role can be granted users.view without users.manage.
 */
Route::middleware('permission:users.view,users.manage')->group(function () {
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])
        ->middleware('permission:users.manage')->name('users.create');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])
        ->middleware('permission:users.manage')->name('users.store');
    Route::get('/users/{user}', [\App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])
        ->middleware('permission:users.manage')->name('users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])
        ->middleware('permission:users.manage')->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])
        ->middleware('permission:users.manage')->name('users.destroy');
});

/**
 * Action Effects Reference
 *
 * Documents what each action in the application triggers elsewhere. Rendered
 * straight from App\Support\Nav\ActionCatalog, which is the same table the
 * post-action notification panel reads its headline from.
 */
Route::get('/reference/action-effects', [\App\Http\Controllers\ActionEffectsController::class, 'index'])
    ->middleware('permission:reference.view')
    ->name('reference.action-effects');
