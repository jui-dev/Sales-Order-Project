<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default system user if it doesn't exist
        User::firstOrCreate(
            ['email' => 'system@example.com'],
            [
                'name' => 'System User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
} 