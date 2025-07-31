<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockLocation;
use App\Models\Retailer;
use App\Models\Warehouse;

class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        // Add warehouses to stock_locations (updateOrCreate to avoid duplicates)
        $warehouses = Warehouse::all();
        foreach ($warehouses as $warehouse) {
            StockLocation::updateOrCreate(
                ['name' => $warehouse->name, 'type' => 'warehouse'],
                [
                    'name' => $warehouse->name,
                    'type' => 'warehouse',
                    'address' => $warehouse->address,
                    'location_id' => $warehouse->id,
                    'location_source' => 'warehouse',
                    'contact_person' => $warehouse->contact_person,
                    'contact_number' => $warehouse->contact_number,
                    'email' => $warehouse->email,
                    'status' => 'active',
                    'is_default' => false,
                ]
            );
        }

        // Add retailers to stock_locations (updateOrCreate to avoid duplicates)
        $retailers = Retailer::all();
        foreach ($retailers as $retailer) {
            StockLocation::updateOrCreate(
                ['name' => $retailer->name, 'type' => 'retailer'],
                [
                    'name' => $retailer->name,
                    'type' => 'retailer',
                    'address' => $retailer->address,
                    'location_id' => $retailer->id,
                    'location_source' => 'retailer',
                    'contact_person' => $retailer->contact_person, // Sync contact person from retailer
                    'contact_number' => $retailer->contact_number, // Use contact_number instead of phone
                    'email' => $retailer->email,
                    'status' => 'active',
                    'is_default' => false,
                ]
            );
        }

        $totalLocations = StockLocation::count();
        $warehouseCount = StockLocation::where('type', 'warehouse')->count();
        $retailerCount = StockLocation::where('type', 'retailer')->count();

        $this->command->info("Stock locations updated: {$totalLocations} total locations ({$warehouseCount} warehouses, {$retailerCount} retailers).");
        $this->command->info("Location IDs and contact information have been synced.");
    }
} 