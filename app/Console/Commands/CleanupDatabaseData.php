<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\IdSequenceService;

class CleanupDatabaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:cleanup-data {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean all data from specified tables while preserving database architecture and columns';

    /**
     * Tables to be cleaned up in order (respecting foreign key constraints)
     *
     * @var array
     */
    protected $tablesToCleanup = [
        // Child tables first (to avoid foreign key constraint violations)
        'audit_logs',
        'credit_note_items',
        'debit_note_items',
        'invoice_items',
        'order_items',
        'payment_items',
        'picking_list_items',
        'stock_transfer_items',
        'supplier_bill_items',
        'supply_items',
        'journal_entry_lines',
        'stock_transactions',
        'product_stocks',
        
        // Parent tables
        'credit_notes',
        'debit_notes',
        'invoices',
        'orders',
        'payments',
        'picking_lists',
        'stock_transfers',
        'supplier_bills',
        'supplier_bill_payments',
        'supplies',
        'grns',
        'journal_entries',
        'products',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Database Cleanup Tool');
        $this->info('========================');
        $this->newLine();

        // Check if we're in production
        if (app()->environment('production')) {
            $this->error('❌ This command cannot be run in production environment!');
            return 1;
        }

        // Display tables that will be cleaned
        $this->info('📋 Tables to be cleaned:');
        foreach ($this->tablesToCleanup as $index => $table) {
            $this->line(sprintf('  %d. %s', $index + 1, $table));
        }
        $this->newLine();

        // Show current data counts
        $this->info('📊 Current data counts:');
        $this->displayTableCounts();
        $this->newLine();

        // Confirmation
        if (!$this->option('confirm')) {
            if (!$this->confirm('⚠️  This will permanently delete ALL data from the specified tables. Are you sure you want to continue?')) {
                $this->info('❌ Operation cancelled.');
                return 0;
            }

            if (!$this->confirm('🔒 Final confirmation: This action cannot be undone. Proceed with cleanup?')) {
                $this->info('❌ Operation cancelled.');
                return 0;
            }
        }

        $this->newLine();
        $this->info('🚀 Starting database cleanup...');
        $this->newLine();

        try {
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $successCount = 0;
            $errorCount = 0;

            foreach ($this->tablesToCleanup as $table) {
                if (Schema::hasTable($table)) {
                    try {
                        $count = DB::table($table)->count();
                        
                        if ($count > 0) {
                            DB::table($table)->truncate();
                            $this->info("✅ Cleaned table: {$table} ({$count} records)");
                            $successCount++;
                        } else {
                            $this->line("ℹ️  Table {$table} is already empty");
                        }
                    } catch (\Exception $e) {
                        $this->error("❌ Error cleaning table {$table}: " . $e->getMessage());
                        $errorCount++;
                    }
                } else {
                    $this->warn("⚠️  Table {$table} does not exist, skipping...");
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // Reset ID sequences for empty tables
            $this->newLine();
            $this->info('🔄 Resetting ID sequences for empty tables...');
            $this->resetIdSequences();

            $this->newLine();
            $this->info('📊 Cleanup Summary:');
            $this->info("✅ Successfully cleaned: {$successCount} tables");
            if ($errorCount > 0) {
                $this->error("❌ Errors encountered: {$errorCount} tables");
            }

            // Show final data counts
            $this->newLine();
            $this->info('📊 Final data counts:');
            $this->displayTableCounts();

            $this->newLine();
            $this->info('🎉 Database cleanup completed successfully!');
            $this->info('💡 All table structures and columns have been preserved.');
            $this->info('💡 Foreign key relationships remain intact.');
            $this->info('💡 ID sequences have been reset for empty tables.');

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->error('❌ An error occurred during cleanup: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Display current table counts
     */
    private function displayTableCounts()
    {
        $counts = [];
        
        foreach ($this->tablesToCleanup as $table) {
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    $counts[] = [$table, $count];
                } catch (\Exception $e) {
                    $counts[] = [$table, 'Error'];
                }
            } else {
                $counts[] = [$table, 'N/A'];
            }
        }

        $this->table(['Table', 'Record Count'], $counts);
    }

    /**
     * Reset ID sequences for empty tables
     */
    private function resetIdSequences(): void
    {
        $resetCount = 0;
        $errorCount = 0;

        foreach ($this->tablesToCleanup as $table) {
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    
                    if ($count === 0) {
                        $success = IdSequenceService::resetSequence($table);
                        if ($success) {
                            $this->info("✅ Reset ID sequence for table: {$table}");
                            $resetCount++;
                        } else {
                            $this->warn("⚠️  Could not reset ID sequence for table: {$table}");
                            $errorCount++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Error resetting ID sequence for table {$table}: " . $e->getMessage());
                    $errorCount++;
                }
            }
        }

        $this->newLine();
        $this->info("📊 ID Sequence Reset Summary:");
        $this->info("✅ Successfully reset: {$resetCount} sequences");
        if ($errorCount > 0) {
            $this->error("❌ Errors encountered: {$errorCount} sequences");
        }
    }
} 