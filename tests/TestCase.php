<?php

namespace Tests;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuditLogUserExists();
        $this->ensureChartOfAccountsExists();
    }

    /**
     * A fresh user that clears every permission gate.
     *
     * setUp() already signs in as an admin, but a test that wants a user
     * object of its own used to reach for User::factory() and sign that in
     * instead - which was harmless only while the routes were ungated. Now
     * that every route checks a permission, a test about rendering a page
     * needs a user who is allowed to see it, not a user with no roles at all.
     */
    protected function adminUser(): User
    {
        if (! Role::query()->where('name', Role::ADMIN)->exists()) {
            $this->seed(RolePermissionSeeder::class);
        }

        $user = User::factory()->create();
        $user->roles()->sync([Role::query()->where('name', Role::ADMIN)->value('id')]);
        $user->forgetCachedPermissions();

        return $user;
    }

    /**
     * Seed the chart of accounts once the schema is in place.
     *
     * AccountingService resolves ledger accounts by code and throws
     * "Account not found for line." when one is missing, so any test that
     * touches invoicing, payments or returns needs the chart present.
     */
    private function ensureChartOfAccountsExists(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        // A migration pre-inserts Retained Earnings (3010), so "any account
        // exists" is not a reliable signal — check for one the seeder owns.
        if (! Account::query()->where('code', '1000')->exists()) {
            $this->seed(ChartOfAccountsSeeder::class);
        }
    }

    /**
     * Guarantee that user #1 exists once the schema is in place.
     *
     * Roughly thirty call sites across observers and services write audit logs
     * with `auth()->id() ?? 1`. When nothing is authenticated that fallback
     * points at a user that does not exist, and the audit_logs foreign key
     * rejects the insert — failing tests that never meant to exercise auth.
     */
    private function ensureAuditLogUserExists(): void
    {
        // Unit tests that skip RefreshDatabase have no schema; nothing to do.
        if (! Schema::hasTable('users')) {
            return;
        }

        $user = User::query()->find(1) ?? User::factory()->create(['id' => 1]);

        // Every route now sits behind the auth middleware, so a test that only
        // meant to exercise a controller would otherwise get a 302 to /login.
        // User #1 is made an admin so it clears every gate, matching how the
        // seeded application actually runs.
        if (! Role::query()->where('name', Role::ADMIN)->exists()) {
            $this->seed(RolePermissionSeeder::class);
        }

        $adminRole = Role::query()->where('name', Role::ADMIN)->first();
        $user->roles()->syncWithoutDetaching([$adminRole->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user);
    }
}
