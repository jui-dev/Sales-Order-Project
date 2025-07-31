<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Add individual seeders here
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            VendorSeeder::class,
            WarehouseSeeder::class,
            RetailerSeeder::class,
            ChartOfAccountsSeeder::class,
            StockLocationSeeder::class,
        ]);
    }
} 