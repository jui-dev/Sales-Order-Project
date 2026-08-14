<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the duplicate Accounts Payable account at code 2100.
 *
 * Supplier bills post to 2000 and vendor returns used to post to 2100, so a
 * return could never offset the bill that created the debt. The return code now
 * posts to 2000 and 2100 has no reason to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $account = DB::table('accounts')->where('code', '2100')->first();

        if (! $account) {
            return;
        }

        // Only remove it if nothing was ever posted against it. Where a database
        // does carry history on 2100, the account is left in place rather than
        // orphaning its lines - that history needs a reclassification entry, not
        // a migration.
        $hasLines = DB::table('journal_entry_lines')->where('account_id', $account->id)->exists();
        $hasChildren = DB::table('accounts')->where('parent_id', $account->id)->exists();

        if ($hasLines || $hasChildren) {
            return;
        }

        DB::table('accounts')->where('id', $account->id)->delete();
    }

    public function down(): void
    {
        // Recreated by ChartOfAccountsSeeder's history, not here: reinstating a
        // duplicate Accounts Payable is never the desired state.
    }
};
