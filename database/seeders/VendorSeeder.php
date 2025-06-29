<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            [
                'id'              => 4001,
                'name'           => 'Global Tech Supplies',
                'contact_person' => 'John Doe',
                'email'          => 'contact@globaltech.com',
                'phone'          => '+1 555-0200',
                'address'        => '1010 Industrial Ave, Springfield',
            ],
            [
                'id'              => 4002,
                'name'           => 'Office Essentials Co.',
                'contact_person' => 'Jane Smith',
                'email'          => 'sales@officeessentials.com',
                'phone'          => '+1 555-0201',
                'address'        => '2020 Commerce Blvd, Springfield',
            ],
            [
                'id'              => 4003,
                'name'           => 'Warehouse World Ltd.',
                'contact_person' => 'Mike Johnson',
                'email'          => 'info@warehouseworld.com',
                'phone'          => '+1 555-0202',
                'address'        => '3030 Supply Rd, Springfield',
            ],
        ];

        foreach ($vendors as $data) {
            Vendor::updateOrCreate(['id' => $data['id']], $data);
        }
    }
} 