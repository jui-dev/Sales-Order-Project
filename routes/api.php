<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sales Order Management System
|--------------------------------------------------------------------------
|
| API endpoints for the Sales Order Management System
| All endpoints use consistent error handling and response formats
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'Sales Order Management System API is running']);
});

// -----------------------------------------------------------------------------
// Core Business API Endpoints
// -----------------------------------------------------------------------------

// Products API
Route::prefix('products')->group(function () {
    Route::get('/', [App\Http\Controllers\ProductController::class, 'apiIndex'])->name('api.products.index');
    Route::get('/{id}', [App\Http\Controllers\ProductController::class, 'apiShow'])->name('api.products.show');
    Route::post('/{id}/recalculate-stocks', [App\Http\Controllers\ProductController::class, 'recalculateStocks'])->name('api.products.recalculate-stocks');
});

// Orders API
Route::prefix('orders')->group(function () {
    Route::get('/', [App\Http\Controllers\OrderController::class, 'apiIndex'])->name('api.orders.index');
    Route::get('/{id}', [App\Http\Controllers\OrderController::class, 'apiShow'])->name('api.orders.show');
});

// Invoices API
Route::prefix('invoices')->group(function () {
    Route::get('/', [App\Http\Controllers\InvoiceController::class, 'apiIndex'])->name('api.invoices.index');
    Route::get('/{id}', [App\Http\Controllers\InvoiceController::class, 'apiShow'])->name('api.invoices.show');
});

// Returns API
Route::prefix('returns')->group(function () {
    Route::get('/', [App\Http\Controllers\ReturnController::class, 'apiIndex'])->name('api.returns.index');
    Route::get('/{id}', [App\Http\Controllers\ReturnController::class, 'apiShow'])->name('api.returns.show');
});

// Credit Notes API
Route::prefix('credit-notes')->group(function () {
    Route::get('/', [App\Http\Controllers\CreditNoteController::class, 'apiIndex'])->name('api.credit-notes.index');
    Route::get('/{id}', [App\Http\Controllers\CreditNoteController::class, 'apiShow'])->name('api.credit-notes.show');
});

// Debit Notes API
Route::prefix('debit-notes')->group(function () {
    Route::get('/', [App\Http\Controllers\DebitNoteController::class, 'apiIndex'])->name('api.debit-notes.index');
    Route::get('/{id}', [App\Http\Controllers\DebitNoteController::class, 'apiShow'])->name('api.debit-notes.show');
});

// Payments API
Route::prefix('payments')->group(function () {
    Route::get('/', [App\Http\Controllers\PaymentController::class, 'apiIndex'])->name('api.payments.index');
    Route::get('/{id}', [App\Http\Controllers\PaymentController::class, 'apiShow'])->name('api.payments.show');
});

// Stock Management API
Route::prefix('stock-management')->group(function () {
    Route::get('/', [App\Http\Controllers\StockManagementController::class, 'apiIndex'])->name('api.stock-management.index');
    Route::get('/{id}', [App\Http\Controllers\StockManagementController::class, 'apiShow'])->name('api.stock-management.show');
});

// Chart of Accounts API
Route::prefix('chart-of-accounts')->group(function () {
    Route::get('/', [App\Http\Controllers\ChartOfAccountsController::class, 'apiIndex'])->name('api.chart-of-accounts.index');
    Route::get('/{id}', [App\Http\Controllers\ChartOfAccountsController::class, 'apiShow'])->name('api.chart-of-accounts.show');
});

// -----------------------------------------------------------------------------
// Supporting API Endpoints
// -----------------------------------------------------------------------------

// Customers API
Route::prefix('customers')->group(function () {
    Route::get('/', [App\Http\Controllers\CustomerController::class, 'apiIndex'])->name('api.customers.index');
    Route::get('/{id}', [App\Http\Controllers\CustomerController::class, 'apiShow'])->name('api.customers.show');
});

// Vendors API
Route::prefix('vendors')->group(function () {
    Route::get('/', [App\Http\Controllers\VendorController::class, 'apiIndex'])->name('api.vendors.index');
    Route::get('/{id}', [App\Http\Controllers\VendorController::class, 'apiShow'])->name('api.vendors.show');
});

// Warehouses API
Route::prefix('warehouses')->group(function () {
    Route::get('/', [App\Http\Controllers\WarehouseController::class, 'apiIndex'])->name('api.warehouses.index');
    Route::get('/{id}', [App\Http\Controllers\WarehouseController::class, 'apiShow'])->name('api.warehouses.show');
});

// Supplies API
Route::prefix('supplies')->group(function () {
    Route::get('/', [App\Http\Controllers\SupplyController::class, 'apiIndex'])->name('api.supplies.index');
    Route::get('/{id}', [App\Http\Controllers\SupplyController::class, 'apiShow'])->name('api.supplies.show');
});

// -----------------------------------------------------------------------------
// Stock Information API (Legacy - kept for compatibility)
// -----------------------------------------------------------------------------

// Stock information for a given warehouse
Route::get('/stock-info/location/{warehouse}', function (App\Models\Warehouse $warehouse) {
    $stock = App\Models\ProductStock::where('location_type', App\Models\Warehouse::class)
        ->where('location_id', $warehouse->id)
        ->get()
        ->pluck('quantity', 'product_id');

    return response()->json($stock);
});

// -----------------------------------------------------------------------------
// Returns a JSON list of locations (warehouse / retailer / other) that have
// available stock for the requested product(s). The response shape is:
//   {
//     "available_locations": [
//       { id, type, name, available_quantity }
//     ]
//   }
// The front-end currently only requests a single product id, but the endpoint
// accepts an array so it can be extended later.
Route::get('/orders/available-fulfillment-locations', function (\Illuminate\Http\Request $request) {
    try {
        // Get product IDs from request
        $productIds = $request->query('product_ids', []);
        
        // Handle array format
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true) ?? [$productIds];
        }
        
        // Handle product_ids[] format
        if (empty($productIds)) {
            $productIds = (array) $request->query('product_ids', []);
        }

        if (empty($productIds)) {
            return response()->json(['available_locations' => []]);
        }

        // Get stock data
        $stockRows = \App\Models\ProductStock::whereIn('product_id', $productIds)->get();
        $validLocations = [];

        foreach ($stockRows as $row) {
            // Calculate available quantity
            $availableQty = (int) $row->quantity - (int) ($row->reserved_quantity ?? 0);
            if ($availableQty <= 0) {
                continue;
            }

            // Determine location type
            $typeKey = strtolower(class_basename($row->location_type));
            
            // Get location model
            $locationModel = null;
            if ($typeKey === 'warehouse') {
                $locationModel = \App\Models\Warehouse::find($row->location_id);
            } elseif ($typeKey === 'retailer') {
                $locationModel = \App\Models\Retailer::find($row->location_id);
            }

            if ($locationModel) {
                $validLocations[] = [
                    'id' => $row->location_id,
                    'type' => $typeKey,
                    'name' => $locationModel->name,
                    'available_quantity' => $availableQty,
                ];
            }
        }

        return response()->json(['available_locations' => $validLocations]);
    } catch (\Exception $e) {
        \Log::error('Error in fulfillment locations API: ' . $e->getMessage());
        return response()->json(['error' => 'Internal server error'], 500);
    }
});

// Alternative route for testing (keeping as backup)
Route::get('/fulfillment-locations', function (\Illuminate\Http\Request $request) {
    try {
        // Get product IDs from request
        $productIds = $request->query('product_ids', []);
        
        // Handle array format
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true) ?? [$productIds];
        }
        
        // Handle product_ids[] format
        if (empty($productIds)) {
            $productIds = (array) $request->query('product_ids', []);
        }

        if (empty($productIds)) {
            return response()->json(['available_locations' => []]);
        }

        // Get stock data
        $stockRows = \App\Models\ProductStock::whereIn('product_id', $productIds)->get();
        $validLocations = [];

        foreach ($stockRows as $row) {
            // Calculate available quantity
            $availableQty = (int) $row->quantity - (int) ($row->reserved_quantity ?? 0);
            if ($availableQty <= 0) {
                continue;
            }

            // Determine location type
            $typeKey = strtolower(class_basename($row->location_type));
            
            // Get location model
            $locationModel = null;
            if ($typeKey === 'warehouse') {
                $locationModel = \App\Models\Warehouse::find($row->location_id);
            } elseif ($typeKey === 'retailer') {
                $locationModel = \App\Models\Retailer::find($row->location_id);
            }

            if ($locationModel) {
                $validLocations[] = [
                    'id' => $row->location_id,
                    'type' => $typeKey,
                    'name' => $locationModel->name,
                    'available_quantity' => $availableQty,
                ];
            }
        }

        return response()->json(['available_locations' => $validLocations]);
    } catch (\Exception $e) {
        \Log::error('Error in fulfillment locations API: ' . $e->getMessage());
        return response()->json(['error' => 'Internal server error'], 500);
    }
}); 