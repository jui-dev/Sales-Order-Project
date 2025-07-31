<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Current Stock Locations - Warehouses:\n";
echo "====================================\n";

$warehouseLocations = DB::table('stock_locations')
    ->where('type', 'warehouse')
    ->get();

foreach ($warehouseLocations as $location) {
    echo "ID: {$location->id}, Name: {$location->name}\n";
    echo "  - Location ID: " . ($location->location_id ?? 'NULL') . "\n";
    echo "  - Source: " . ($location->location_source ?? 'NULL') . "\n";
    echo "  - Contact Person: " . ($location->contact_person ?? 'NULL') . "\n";
    echo "  - Contact Number: " . ($location->contact_number ?? 'NULL') . "\n";
    echo "  - Email: " . ($location->email ?? 'NULL') . "\n";
    echo "\n";
}

echo "Expected Warehouse Data:\n";
echo "=======================\n";

$warehouses = DB::table('warehouses')->get();
foreach ($warehouses as $warehouse) {
    echo "ID: {$warehouse->id}, Name: {$warehouse->name}\n";
    echo "  - Contact Person: " . ($warehouse->contact_person ?? 'NULL') . "\n";
    echo "  - Contact Number: " . ($warehouse->contact_number ?? 'NULL') . "\n";
    echo "  - Email: " . ($warehouse->email ?? 'NULL') . "\n";
    echo "\n";
} 