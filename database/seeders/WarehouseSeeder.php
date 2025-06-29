<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent insert: avoids duplicates when re-running seeds
        $data = [
            [
                'id'      => 1001,
                'name'    => 'Main Distribution Center',
                'address' => '123 Logistics Ave, Springfield',
                'contact_person' => 'John Doe',
                'contact_number' => '555-1001',
                'email' => 'main-dc@example.com',
                'status' => 'active',
                'is_default' => true,
            ],
            [
                'id'      => 1002,
                'name'    => 'Regional Warehouse East',
                'address' => '456 Commerce Rd, Lakeside',
                'contact_person' => 'Jane Smith',
                'contact_number' => '555-1002',
                'email' => 'east-warehouse@example.com',
                'status' => 'active',
                'is_default' => false,
            ],
        ];

        foreach ($data as $attrs) {
            Warehouse::updateOrCreate(['id' => $attrs['id']], $attrs);
        }
    }
} 