<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\IdSequenceService;

class ManageIdSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'id:manage 
                            {action : Action to perform (status|sync|reset|initialize|cleanup)}
                            {--table= : Specific table to operate on}
                            {--all : Operate on all tables}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage ID sequences and trackers for database tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $specificTable = $this->option('table');
        $operateAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        $this->info('ID Sequence Management Tool');
        $this->info('========================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Determine which tables to process
        $tables = $this->getTablesToProcess($specificTable, $operateAll);
        
        if (empty($tables)) {
            $this->error('No tables specified. Use --table=TABLE_NAME or --all');
            return 1;
        }

        switch ($action) {
            case 'status':
                $this->showStatus($tables);
                break;
            case 'sync':
                $this->syncTrackers($tables, $dryRun);
                break;
            case 'reset':
                $this->resetSequences($tables, $dryRun);
                break;
            case 'initialize':
                $this->initializeTrackers($tables, $dryRun);
                break;
            case 'cleanup':
                $this->cleanupOrphanedTrackers($dryRun);
                break;
            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: status, sync, reset, initialize, cleanup');
                return 1;
        }

        return 0;
    }

    /**
     * Get tables to process based on options
     */
    private function getTablesToProcess(?string $specificTable, bool $operateAll): array
    {
        if ($specificTable) {
            if (!Schema::hasTable($specificTable)) {
                $this->error("Table '{$specificTable}' does not exist!");
                return [];
            }
            return [$specificTable];
        }

        if ($operateAll) {
            $tablesResult = DB::select("SHOW TABLES");
            $tables = [];
            foreach ($tablesResult as $table) {
                $tableName = array_values((array) $table)[0];
                // Skip the tracker table itself
                if ($tableName !== 'id_sequence_tracker') {
                    $tables[] = $tableName;
                }
            }
            return $tables;
        }

        return [];
    }

    /**
     * Show status of ID sequences for tables
     */
    private function showStatus(array $tables): void
    {
        $this->info('ID Sequence Status');
        $this->info('==================');
        $this->newLine();

        $headers = ['Table', 'Records', 'Max ID', 'Last Assigned', 'Next ID', 'Status'];
        $rows = [];

        foreach ($tables as $tableName) {
            try {
                $recordCount = DB::table($tableName)->count();
                $maxId = DB::table($tableName)->max('id') ?? 0;
                
                $trackerInfo = IdSequenceService::getTrackerInfo($tableName);
                
                if ($trackerInfo) {
                    $lastAssigned = $trackerInfo['last_assigned_id'];
                    $nextId = $trackerInfo['next_id'];
                    $status = $this->getStatusIndicator($trackerInfo);
                } else {
                    $lastAssigned = 'N/A';
                    $nextId = $maxId + 1;
                    $status = '⚠️  No Tracker';
                }

                $rows[] = [
                    $tableName,
                    $recordCount,
                    $maxId,
                    $lastAssigned,
                    $nextId,
                    $status
                ];

            } catch (\Exception $e) {
                $rows[] = [
                    $tableName,
                    'Error',
                    'Error',
                    'Error',
                    'Error',
                    '❌ Error'
                ];
            }
        }

        $this->table($headers, $rows);
    }

    /**
     * Get status indicator for tracker info
     */
    private function getStatusIndicator(array $trackerInfo): string
    {
        $lastAssigned = $trackerInfo['last_assigned_id'];
        $actualMax = $trackerInfo['actual_max_id'];
        
        if ($lastAssigned == $actualMax) {
            return '✅ Synced';
        } elseif ($lastAssigned < $actualMax) {
            return '⚠️  Behind';
        } else {
            return '❌ Ahead';
        }
    }

    /**
     * Sync trackers for tables
     */
    private function syncTrackers(array $tables, bool $dryRun): void
    {
        $this->info('Syncing ID Trackers');
        $this->info('===================');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;

        foreach ($tables as $tableName) {
            try {
                if ($dryRun) {
                    $this->line("🔍 Would sync tracker for table '{$tableName}'");
                } else {
                    $success = IdSequenceService::syncTracker($tableName);
                    if ($success) {
                        $this->info("✅ Synced tracker for table '{$tableName}'");
                        $successCount++;
                    } else {
                        $this->error("❌ Failed to sync tracker for table '{$tableName}'");
                        $errorCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error syncing tracker for table '{$tableName}': " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary: {$successCount} successful, {$errorCount} errors");
    }

    /**
     * Reset sequences for tables (only if empty)
     */
    private function resetSequences(array $tables, bool $dryRun): void
    {
        $this->info('Resetting ID Sequences');
        $this->info('======================');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($tables as $tableName) {
            try {
                $recordCount = DB::table($tableName)->count();
                
                if ($recordCount > 0) {
                    $this->warn("⚠️  Skipping table '{$tableName}' (not empty: {$recordCount} records)");
                    $skippedCount++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("🔍 Would reset sequence for table '{$tableName}'");
                } else {
                    $success = IdSequenceService::resetSequence($tableName);
                    if ($success) {
                        $this->info("✅ Reset sequence for table '{$tableName}'");
                        $successCount++;
                    } else {
                        $this->error("❌ Failed to reset sequence for table '{$tableName}'");
                        $errorCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error resetting sequence for table '{$tableName}': " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary: {$successCount} successful, {$errorCount} errors, {$skippedCount} skipped");
    }

    /**
     * Initialize trackers for tables
     */
    private function initializeTrackers(array $tables, bool $dryRun): void
    {
        $this->info('Initializing ID Trackers');
        $this->info('========================');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;

        foreach ($tables as $tableName) {
            try {
                if ($dryRun) {
                    $this->line("🔍 Would initialize tracker for table '{$tableName}'");
                } else {
                    $success = IdSequenceService::syncTracker($tableName);
                    if ($success) {
                        $this->info("✅ Initialized tracker for table '{$tableName}'");
                        $successCount++;
                    } else {
                        $this->error("❌ Failed to initialize tracker for table '{$tableName}'");
                        $errorCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error initializing tracker for table '{$tableName}': " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary: {$successCount} successful, {$errorCount} errors");
    }

    /**
     * Clean up orphaned tracker entries
     */
    private function cleanupOrphanedTrackers(bool $dryRun): void
    {
        $this->info('Cleaning Up Orphaned Trackers');
        $this->info('=============================');
        $this->newLine();

        if ($dryRun) {
            $this->line("🔍 Would clean up orphaned tracker entries");
        } else {
            $deletedCount = IdSequenceService::cleanupOrphanedTrackers();
            $this->info("✅ Cleaned up {$deletedCount} orphaned tracker entries");
        }
    }
} 