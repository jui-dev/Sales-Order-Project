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
    $productIds = (array) $request->query('product_ids', []);

    if (empty($productIds)) {
        return response()->json(['available_locations' => []]);
    }

    // Fetch stock for the requested product(s)
    $stockRows = \App\Models\ProductStock::query()
        ->whereIn('product_id', $productIds)
        ->where('quantity', '>', 0)
        ->get();

    // Group by location so we can sum quantities per location (in case there
    // are multiple product ids in the request).
    $locationData = [];

    foreach ($stockRows as $row) {
        $locKey = $row->location_type . ':' . $row->location_id;

        if (! isset($locationData[$locKey])) {
            // Resolve the polymorphic location model to fetch the name/type
            $locationModel = $row->location_type::find($row->location_id);

            $locationData[$locKey] = [
                'id'                => $row->location_id,
                'type'              => class_basename($row->location_type) === 'Warehouse' ? 'warehouse'
                    : (class_basename($row->location_type) === 'Retailer' ? 'retailer' : 'other'),
                'name'              => $locationModel->name ?? ('Location #' . $row->location_id),
                'available_quantity'=> 0,
            ];
        }

        // Sum quantities per location across requested products
        $locationData[$locKey]['available_quantity'] += (int) $row->quantity;
    }

    return response()->json(['available_locations' => array_values($locationData)]);
});
// ----------------------------------------------------------------------------- 