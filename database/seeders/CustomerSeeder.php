<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'id'      => 3001,
                'name'    => 'Alice Johnson',
                'email'   => 'alice@example.com',
                'phone'   => '+1 555-0100',
                'address' => '123 Main St, Springfield',
            ],
            [
                'id'      => 3002,
                'name'    => 'Bob Smith',
                'email'   => 'bob@example.com',
                'phone'   => '+1 555-0101',
                'address' => '456 Elm St, Springfield',
            ],
            [
                'id'      => 3003,
                'name'    => 'Carol Williams',
                'email'   => 'carol@example.com',
                'phone'   => '+1 555-0102',
                'address' => '789 Oak St, Springfield',
            ],
            [
                'id'      => 3004,
                'name'    => 'Daniel Thompson',
                'email'   => 'daniel@example.com',
                'phone'   => '+1 555-0103',
                'address' => '321 Pine St, Springfield',
            ],
        ];

        foreach ($samples as $data) {
            Customer::updateOrCreate([
                'id' => $data['id']
            ], $data);
        }
    }
} 