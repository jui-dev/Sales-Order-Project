<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Promotes user #1 into the administrator account rather than creating a
     * second user. Roughly thirty call sites across observers and services
     * attribute their work with `auth()->id() ?? 1`, and the audit_logs foreign
     * key points at that same row - so reusing it keeps existing history
     * attributed to a real, reachable login.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $adminRole = Role::where('name', Role::ADMIN)->first();

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
