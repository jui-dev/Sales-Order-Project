<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose `status` enum was widened by later MySQL-only migrations.
     *
     * Those migrations issue raw `ALTER TABLE ... MODIFY COLUMN status ENUM(...)`
     * and are skipped on every other driver, so on SQLite the column keeps the
     * original enum from the initial schema. Laravel renders an enum on SQLite as
     * a CHECK constraint, which then rejects the status values the application
     * actually writes (for example 'pending' on picking_lists).
     *
     * Converting the column to a plain string on those drivers drops the stale
     * CHECK constraint and lets the test database accept the same values MySQL
     * does. MySQL is left untouched — it keeps its real enums.
     *
     * @var array<string, string> table => default status
     */
    private array $tables = [
        'supplies' => 'pending',
        'orders' => 'pending',
        'picking_lists' => 'open',
        'stock_transactions' => 'pending',
        'credit_notes' => 'draft',
        'debit_notes' => 'draft',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return; // MySQL gets the widened enums from the ALTER ENUM migrations.
        }

        foreach ($this->tables as $table => $default) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($default) {
                $blueprint->string('status')->default($default)->change();
            });
        }
    }

    public function down(): void
    {
        // Irreversible by design: the original enum sets live in the migrations
        // that created them, and restoring a CHECK constraint here would undo
        // the widening those MySQL-only migrations represent.
    }
};
