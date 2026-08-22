<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild the ledger around dimensions, entry origin and fiscal periods.
 *
 * The existing ledger is discarded rather than migrated. It can be reproduced
 * exactly from the business documents with the accounting:rebuild command,
 * which is the point of the redesign: the ledger is a function of the
 * documents, so there is no history here that only the ledger knows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Chart of accounts: control accounts, location analysis, rollups.
        // ------------------------------------------------------------------
        Schema::table('accounts', function (Blueprint $table) {
            // A rollup account exists to total its children and must never be
            // posted to directly.
            $table->boolean('is_postable')->default(true)->after('is_contra');

            // 'customer' or 'vendor'. Every line against a control account has
            // to carry that party, which is what makes the account
            // reconcilable against its subsidiary ledger.
            $table->string('control_of', 20)->nullable()->after('is_postable');

            // Inventory is analysed by stock location, so every line against
            // it must say which location it belongs to.
            $table->boolean('requires_location')->default(false)->after('control_of');
        });

        // Opening balances are an opening journal entry now, not a column that
        // every report forgot to read.
        if (Schema::hasColumn('accounts', 'opening_balance')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('opening_balance');
            });
        }

        // ------------------------------------------------------------------
        // Journal entries: where the entry came from.
        // ------------------------------------------------------------------
        Schema::table('journal_entries', function (Blueprint $table) {
            // 'system' entries are the ledger's own record of a confirmed
            // document and are posted on creation; 'manual' entries keep the
            // draft -> approved -> posted review path, which is where
            // segregation of duties actually belongs.
            $table->string('origin', 10)->default('system')->after('status');

            // Idempotency is per document *and* per rule: one GRN raises a
            // goods-receipt entry, and the bill against it raises another.
            $table->string('rule_key')->nullable()->after('source_id');

            $table->index(['status', 'entry_date']);
            $table->unique(['source_type', 'source_id', 'rule_key'], 'journal_entries_source_rule_unique');
        });

        // ------------------------------------------------------------------
        // Journal lines: dimensions instead of an account per thing.
        // ------------------------------------------------------------------
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->nullableMorphs('party');       // customer / vendor
            $table->nullableMorphs('location');    // warehouse / retailer
            $table->foreignId('product_id')->nullable()->after('location_id')->constrained('products')->nullOnDelete();

            // Designed for, not exercised: every amount is in base currency
            // until there is a second one.
            $table->string('currency', 3)->nullable()->after('credit');
            $table->decimal('fx_rate', 18, 8)->nullable()->after('currency');

            $table->index(['account_id', 'journal_entry_id'], 'jel_account_entry_index');
        });

        // ------------------------------------------------------------------
        // Fiscal periods.
        // ------------------------------------------------------------------
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // 2026-08
            $table->date('starts_on');
            $table->date('ends_on');
            // open   - accepts postings
            // closed - closing entry posted; reopening is possible
            // locked - permanently sealed
            $table->string('status', 10)->default('open');
            $table->foreignId('closing_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        $this->discardExistingLedger();
    }

    /**
     * Empty the ledger and detach every document that pointed into it.
     *
     * The documents themselves are untouched - they are the source the ledger
     * is rebuilt from.
     */
    private function discardExistingLedger(): void
    {
        $detach = [
            'credit_notes'           => ['journal_entry_id'],
            'debit_notes'            => ['journal_entry_id'],
            'supplier_bills'         => ['purchase_journal_id', 'payment_journal_id'],
            'supplier_bill_payments' => ['payment_journal_id'],
        ];

        foreach ($detach as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->update([$column => null]);
                }
            }
        }

        // journal_entries carries a self-reference and two note references;
        // clear them before deleting so the rows can go in any order.
        DB::table('journal_entries')->update([
            'reverses_journal_id'   => null,
            'linked_debit_note_id'  => null,
            'linked_credit_note_id' => null,
        ]);

        DB::table('journal_entry_lines')->delete();
        DB::table('journal_entries')->delete();

        // The per-location inventory sub-accounts are replaced by the location
        // dimension on the line. They only ever carried transfer activity, so
        // nothing is lost by removing them.
        DB::table('accounts')->where('code', 'like', '1200-%')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('jel_account_entry_index');
            $table->dropConstrainedForeignId('product_id');
            $table->dropMorphs('party');
            $table->dropMorphs('location');
            $table->dropColumn(['currency', 'fx_rate']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_source_rule_unique');
            $table->dropIndex(['status', 'entry_date']);
            $table->dropColumn(['origin', 'rule_key']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->dropColumn(['is_postable', 'control_of', 'requires_location']);
        });
    }
};
