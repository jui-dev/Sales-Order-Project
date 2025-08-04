<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BackupDatabaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:backup-data {--tables=* : Specific tables to backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup data from specified tables before cleanup operations';

    /**
     * Tables to be backed up
     *
     * @var array
     */
    protected $tablesToBackup = [
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
        $this->info('💾 Database Backup Tool');
        $this->info('=====================');
        $this->newLine();

        // Check if we're in production
        if (app()->environment('production')) {
            $this->error('❌ This command cannot be run in production environment!');
            return 1;
        }

        // Determine which tables to backup
        $tables = $this->option('tables');
        if (empty($tables)) {
            $tables = $this->tablesToBackup;
        }

        $this->info('📋 Tables to backup:');
        foreach ($tables as $index => $table) {
            $this->line(sprintf('  %d. %s', $index + 1, $table));
        }
        $this->newLine();

        // Show current data counts
        $this->info('📊 Current data counts:');
        $this->displayTableCounts($tables);
        $this->newLine();

        // Confirmation
        if (!$this->confirm('💾 Do you want to create a backup of the current data?')) {
            $this->info('❌ Backup cancelled.');
            return 0;
        }

        $this->newLine();
        $this->info('🚀 Starting database backup...');
        $this->newLine();

        try {
            $backupData = [];
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $backupFileName = "database_backup_{$timestamp}.json";

            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    try {
                        $data = DB::table($table)->get();
                        $count = $data->count();
                        
                        if ($count > 0) {
                            $backupData[$table] = $data->toArray();
                            $this->info("✅ Backed up table: {$table} ({$count} records)");
                        } else {
                            $this->line("ℹ️  Table {$table} is empty, skipping backup");
                        }
                    } catch (\Exception $e) {
                        $this->error("❌ Error backing up table {$table}: " . $e->getMessage());
                    }
                } else {
                    $this->warn("⚠️  Table {$table} does not exist, skipping...");
                }
            }

            // Save backup to file
            if (!empty($backupData)) {
                $backupPath = storage_path("app/backups/{$backupFileName}");
                
                // Create backups directory if it doesn't exist
                if (!file_exists(dirname($backupPath))) {
                    mkdir(dirname($backupPath), 0755, true);
                }

                file_put_contents($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));
                
                $this->newLine();
                $this->info("💾 Backup saved to: {$backupPath}");
                $this->info("📁 Backup size: " . $this->formatBytes(filesize($backupPath)));
                
                $this->newLine();
                $this->info('🎉 Database backup completed successfully!');
                $this->info('💡 You can restore this backup using the restore command if needed.');
            } else {
                $this->warn('⚠️  No data found to backup.');
            }

        } catch (\Exception $e) {
            $this->error('❌ An error occurred during backup: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Display current table counts
     */
    private function displayTableCounts($tables)
    {
        $counts = [];
        
        foreach ($tables as $table) {
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
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 