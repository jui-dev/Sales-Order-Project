<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - UI Only Project
|--------------------------------------------------------------------------
|
| All backend API functionality has been removed.
| This file is kept for future API development.
|
*/

// Placeholder for future API routes
Route::get('/health', function () {
    return response()->json(['status' => 'UI Only - No Backend APIs Available']);
});

// Stock information for a given warehouse
Route::get('/stock-info/location/{warehouse}', function (App\Models\Warehouse $warehouse) {
    $stock = App\Models\ProductStock::where('location_type', App\Models\Warehouse::class)
        ->where('location_id', $warehouse->id)
        ->get()
        ->pluck('quantity', 'product_id');

    return response()->json($stock);
});

// -----------------------------------------------------------------------------
// Order Fulfillment – available locations for selected products
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
    // Handle both single product_ids and array format (product_ids[])
    $productIds = $request->query('product_ids', []);
    
    // If product_ids is a string, try to JSON decode it, otherwise use as array
    if (is_string($productIds)) {
        $productIds = json_decode($productIds, true) ?? [$productIds];
    }
    
    // Also check for product_ids[] format from URLSearchParams
    if (empty($productIds)) {
        $productIds = (array) $request->query('product_ids', []);
    }

    if (empty($productIds)) {
        return response()->json(['available_locations' => []]);
    }

    // Fetch stock rows for the requested products (we will filter availability in PHP to
    // avoid SQL errors if the reserved_quantity column is missing on older schemas).
    $stockRows = \App\Models\ProductStock::query()
        ->whereIn('product_id', $productIds)
        ->get();

    $locationData = [];
    $locationStockData = []; // Track stock per location per product

    foreach ($stockRows as $row) {
        // --------------------------------------------------------------
        // Skip rows that have no free stock ( quantity – reserved <= 0 )
        // --------------------------------------------------------------
        $availableQty = (int) $row->quantity - (int) ($row->reserved_quantity ?? 0);
        if ($availableQty <= 0) {
            continue; // nothing we can fulfil from this location
        }

        /* ------------------------------------------------------------------
         | Resolve the location model. The location_type column may contain:
         | 1) Fully qualified class (e.g. "App\\Models\\Warehouse")
         | 2) Un-qualified class name  (e.g. "Warehouse")
         | 3) Lower-case shorthand      (e.g. "warehouse" | "retailer")
         | We gracefully normalise these to the correct model class.
         |------------------------------------------------------------------*/
        $typeKey = strtolower(class_basename($row->location_type));

        $modelClass = match ($typeKey) {
            'warehouse' => \App\Models\Warehouse::class,
            'retailer'  => \App\Models\Retailer::class,
            default     => $row->location_type, // fallback – assume FQCN
        };

        $locationModel = class_exists($modelClass) ? $modelClass::find($row->location_id) : null;

        // Build locKey using normalised class for uniqueness per location
        $locKey = $modelClass . ':' . $row->location_id;

        if (! isset($locationData[$locKey])) {
            $locationData[$locKey] = [
                'id'                 => $row->location_id,
                'type'               => $typeKey, // "warehouse" | "retailer" | other
                'name'               => $locationModel?->name ?? ('Location #' . $row->location_id),
                'available_quantity' => 0, // will be updated below
            ];
            $locationStockData[$locKey] = [];
        }

        // Track stock per product for this location
        $locationStockData[$locKey][$row->product_id] = $availableQty;
    }

    // For each location, check if it has stock for ALL requested products
    $validLocations = [];
    
    foreach ($locationData as $locKey => $locationInfo) {
        $hasAllProducts = true;
        $minAvailableQty = PHP_INT_MAX;
        
        foreach ($productIds as $productId) {
            if (!isset($locationStockData[$locKey][$productId]) || $locationStockData[$locKey][$productId] <= 0) {
                $hasAllProducts = false;
                break;
            }
            
            // For display purposes, show the minimum available quantity across all products
            $minAvailableQty = min($minAvailableQty, $locationStockData[$locKey][$productId]);
        }
        
        if ($hasAllProducts) {
            $locationInfo['available_quantity'] = $minAvailableQty;
            $validLocations[] = $locationInfo;
        }
    }

    return response()->json(['available_locations' => $validLocations]);
});
// ----------------------------------------------------------------------------- 