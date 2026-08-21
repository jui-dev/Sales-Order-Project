<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Add individual seeders here
        $this->call([
            // Must precede UserSeeder - it needs the admin role to exist.
            RolePermissionSeeder::class,
            UserSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            VendorSeeder::class,
            // After both: it joins products to the vendors that carry them.
            VendorProductSeeder::class,
            WarehouseSeeder::class,
            RetailerSeeder::class,
            ChartOfAccountsSeeder::class,
            StockLocationSeeder::class,
            IdSequenceTrackerSeeder::class,
        ]);
    }
} 