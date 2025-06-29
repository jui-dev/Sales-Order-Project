<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Retailer;

class RetailerSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'id'      => 2001,
                'name'    => 'Downtown Store',
                'email'   => 'downtown@example.com',
                'phone'   => '555-0101',
                'contact_number' => '555-0101',
                'contact_person' => 'Alice Brown',
                'address' => '100 Main St, Springfield',
                'status' => 'active',
                'is_default' => true,
            ],
            [
                'id'      => 2002,
                'name'    => 'Mall Kiosk',
                'email'   => 'mall@example.com',
                'phone'   => '555-0102',
                'contact_number' => '555-0102',
                'contact_person' => 'Bob Davis',
                'address' => '200 Commerce Blvd, Springfield',
                'status' => 'active',
                'is_default' => false,
            ],
            [
                'id'      => 2003,
                'name'    => 'Suburban Outlet',
                'email'   => 'suburb@example.com',
                'phone'   => '555-0103',
                'contact_number' => '555-0103',
                'contact_person' => 'Carol Evans',
                'address' => '300 Oak Dr, Shelbyville',
                'status' => 'active',
                'is_default' => false,
            ],
            [
                'id'      => 2004,
                'name'    => 'Airport Store',
                'email'   => 'airport@example.com',
                'phone'   => '555-0104',
                'contact_number' => '555-0104',
                'contact_person' => 'David Frost',
                'address' => 'Terminal A, Springfield Airport',
                'status' => 'active',
                'is_default' => false,
            ],
        ];

        foreach ($data as $attrs) {
            Retailer::updateOrCreate(['id' => $attrs['id']], $attrs);
        }
    }
} 