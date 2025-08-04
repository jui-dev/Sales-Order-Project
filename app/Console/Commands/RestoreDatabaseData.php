<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class RestoreDatabaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:restore-data {backup-file : The backup file to restore from} {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore data from a backup file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Database Restore Tool');
        $this->info('======================');
        $this->newLine();

        // Check if we're in production
        if (app()->environment('production')) {
            $this->error('❌ This command cannot be run in production environment!');
            return 1;
        }

        $backupFile = $this->argument('backup-file');
        
        // Check if backup file exists
        if (!File::exists($backupFile)) {
            $this->error("❌ Backup file not found: {$backupFile}");
            return 1;
        }

        $this->info("📁 Backup file: {$backupFile}");
        $this->info("📁 File size: " . $this->formatBytes(File::size($backupFile)));
        $this->newLine();

        // Load backup data
        try {
            $backupData = json_decode(File::get($backupFile), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('❌ Invalid backup file format: ' . json_last_error_msg());
                return 1;
            }

            $this->info('📋 Tables in backup:');
            foreach (array_keys($backupData) as $index => $table) {
                $recordCount = count($backupData[$table]);
                $this->line(sprintf('  %d. %s (%d records)', $index + 1, $table, $recordCount));
            }
            $this->newLine();

            // Confirmation
            if (!$this->option('confirm')) {
                if (!$this->confirm('⚠️  This will overwrite existing data in the database. Are you sure you want to continue?')) {
                    $this->info('❌ Restore cancelled.');
                    return 0;
                }

                if (!$this->confirm('🔒 Final confirmation: This action will overwrite current data. Proceed with restore?')) {
                    $this->info('❌ Restore cancelled.');
                    return 0;
                }
            }

            $this->newLine();
            $this->info('🚀 Starting database restore...');
            $this->newLine();

            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            $successCount = 0;
            $errorCount = 0;

            foreach ($backupData as $table => $records) {
                if (Schema::hasTable($table)) {
                    try {
                        // Clear existing data
                        DB::table($table)->truncate();
                        
                        if (!empty($records)) {
                            // Insert backup data
                            DB::table($table)->insert($records);
                            $this->info("✅ Restored table: {$table} (" . count($records) . " records)");
                            $successCount++;
                        } else {
                            $this->line("ℹ️  Table {$table} was empty in backup");
                        }
                    } catch (\Exception $e) {
                        $this->error("❌ Error restoring table {$table}: " . $e->getMessage());
                        $errorCount++;
                    }
                } else {
                    $this->warn("⚠️  Table {$table} does not exist in database, skipping...");
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            $this->newLine();
            $this->info('📊 Restore Summary:');
            $this->info("✅ Successfully restored: {$successCount} tables");
            if ($errorCount > 0) {
                $this->error("❌ Errors encountered: {$errorCount} tables");
            }

            $this->newLine();
            $this->info('🎉 Database restore completed successfully!');

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->error('❌ An error occurred during restore: ' . $e->getMessage());
            return 1;
        }

        return 0;
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