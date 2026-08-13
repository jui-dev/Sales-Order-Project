<?php

namespace Tests;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
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

        if (! User::query()->whereKey(1)->exists()) {
            User::factory()->create(['id' => 1]);
        }
    }
}
