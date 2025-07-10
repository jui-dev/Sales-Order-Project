<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Add individual seeders here
        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
            VendorSeeder::class,
            WarehouseSeeder::class,
            RetailerSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }
} 