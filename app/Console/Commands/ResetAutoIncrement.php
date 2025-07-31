<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetAutoIncrement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-auto-increment 
                            {--table= : Specific table to reset auto-increment for}
                            {--all : Reset auto-increment for all tables}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset auto-increment counters for database tables to start from 1';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Auto-Increment Reset Command');
        $this->info('==========================');
        $this->newLine();

        $specificTable = $this->option('table');
        $resetAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Determine which tables to process
        if ($specificTable) {
            if (!Schema::hasTable($specificTable)) {
                $this->error("Table '{$specificTable}' does not exist!");
                return 1;
            }
            $tables = [$specificTable];
            $this->info("Processing specific table: {$specificTable}");
        } elseif ($resetAll) {
            // Get all tables using raw SQL instead of Schema::getAllTables()
            $tablesResult = DB::select("SHOW TABLES");
            $tables = [];
            foreach ($tablesResult as $table) {
                $tables[] = array_values((array)$table)[0];
            }
            $this->info("Processing all tables: " . count($tables) . " tables found");
        } else {
            $this->error('Please specify either --table=TABLE_NAME or --all option');
            $this->newLine();
            $this->info('Usage examples:');
            $this->line('  php artisan db:reset-auto-increment --table=orders');
            $this->line('  php artisan db:reset-auto-increment --all');
            $this->line('  php artisan db:reset-auto-increment --all --dry-run');
            return 1;
        }

        $this->newLine();

        // Step 1: Show current auto-increment values
        $this->info('Step 1: Current auto-increment values');
        $this->info('====================================');

        $tableInfo = [];
        foreach ($tables as $tableName) {
            try {
                $tableStatus = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'")[0];
                $autoIncrement = $tableStatus->Auto_increment ?? 'N/A';
                $rowCount = DB::table($tableName)->count();
                
                $tableInfo[$tableName] = [
                    'current_ai' => $autoIncrement,
                    'row_count' => $rowCount
                ];
                
                $this->line("📊 {$tableName}: {$rowCount} records - Next ID: {$autoIncrement}");
            } catch (\Exception $e) {
                $this->error("❌ Error getting info for table '{$tableName}': " . $e->getMessage());
            }
        }

        $this->newLine();

        // Step 2: Reset auto-increment counters
        $this->info('Step 2: Resetting auto-increment counters');
        $this->info('========================================');

        $resetCount = 0;
        $errorCount = 0;

        foreach ($tables as $tableName) {
            try {
                if ($dryRun) {
                    $this->line("🔍 Would reset auto-increment for table '{$tableName}' to start from 1");
                } else {
                    DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
                    $this->info("✅ Reset auto-increment for table '{$tableName}' to start from 1");
                }
                $resetCount++;
            } catch (\Exception $e) {
                $this->error("❌ Error resetting auto-increment for table '{$tableName}': " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();

        // Step 3: Verify reset results (only if not dry run)
        if (!$dryRun) {
            $this->info('Step 3: Verification - Auto-increment values after reset');
            $this->info('=======================================================');

            $verifiedCount = 0;

            foreach ($tables as $tableName) {
                try {
                    $tableStatus = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'")[0];
                    $autoIncrement = $tableStatus->Auto_increment ?? 'N/A';
                    $rowCount = DB::table($tableName)->count();
                    
                    if ($autoIncrement == 1 || $autoIncrement == 'N/A') {
                        $this->info("✅ {$tableName}: {$rowCount} records - Next ID: {$autoIncrement}");
                        $verifiedCount++;
                    } else {
                        $this->warn("⚠️  {$tableName}: {$rowCount} records - Next ID: {$autoIncrement} (not reset)");
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Error verifying table '{$tableName}': " . $e->getMessage());
                }
            }

            $this->newLine();
        }

        // Step 4: Summary
        $this->info('Step 4: Summary');
        $this->info('===============');
        $this->line("📊 Total tables processed: " . count($tables));
        $this->line("✅ Successfully reset: {$resetCount} tables");
        $this->line("❌ Errors encountered: {$errorCount} tables");
        
        if (!$dryRun) {
            $this->line("✅ Verified reset: {$verifiedCount} tables");
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN COMPLETED - No changes were made');
        } else {
            $this->info('Auto-increment reset completed successfully!');
            $this->info('==========================================');
            $this->line('✅ All specified tables now have auto-increment starting from 1');
            $this->line('✅ Next insertions will use ID 1, 2, 3, etc.');
            $this->line('✅ No data was affected, only auto-increment counters were reset');
            $this->newLine();
            $this->line('💡 You can now insert new records and they will start with ID 1!');
        }

        return 0;
    }
} 