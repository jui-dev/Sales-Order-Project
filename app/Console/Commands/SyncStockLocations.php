<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncStockLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-stock-locations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stock locations with data from retailers and warehouses tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing stock locations with retailers and warehouses data...');

        // Add warehouses to stock_locations
        $warehouses = \App\Models\Warehouse::all();
        $warehouseCount = 0;
        
        foreach ($warehouses as $warehouse) {
            $location = \App\Models\StockLocation::updateOrCreate(
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
            
            if ($location->wasRecentlyCreated) {
                $warehouseCount++;
            }
        }

        // Add retailers to stock_locations
        $retailers = \App\Models\Retailer::all();
        $retailerCount = 0;
        
        foreach ($retailers as $retailer) {
            $location = \App\Models\StockLocation::updateOrCreate(
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
            
            if ($location->wasRecentlyCreated) {
                $retailerCount++;
            }
        }

        $totalLocations = \App\Models\StockLocation::count();
        $totalWarehouses = \App\Models\StockLocation::where('type', 'warehouse')->count();
        $totalRetailers = \App\Models\StockLocation::where('type', 'retailer')->count();

        $this->info("Sync completed!");
        $this->info("- Added {$warehouseCount} new warehouses");
        $this->info("- Added {$retailerCount} new retailers");
        $this->info("- Total locations: {$totalLocations} ({$totalWarehouses} warehouses, {$totalRetailers} retailers)");
        $this->info("- Location IDs and contact information have been synced.");
    }
}
