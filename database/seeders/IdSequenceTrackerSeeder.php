<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Services\IdSequenceService;

class IdSequenceTrackerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Initializing ID Sequence Tracker...');

        try {
            // Get all tables
            $tables = DB::select("SHOW TABLES");
            $tableNames = [];
            
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                // Skip the tracker table itself
                if ($tableName !== 'id_sequence_tracker') {
                    $tableNames[] = $tableName;
                }
            }

            $this->command->info("Found " . count($tableNames) . " tables to initialize");

            $successCount = 0;
            $errorCount = 0;

            foreach ($tableNames as $tableName) {
                try {
                    $success = IdSequenceService::syncTracker($tableName);
                    
                    if ($success) {
                        $this->command->info("✅ Initialized tracker for table: {$tableName}");
                        $successCount++;
                    } else {
                        $this->command->error("❌ Failed to initialize tracker for table: {$tableName}");
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $this->command->error("❌ Error initializing tracker for table {$tableName}: " . $e->getMessage());
                    $errorCount++;
                }
            }

            $this->command->newLine();
            $this->command->info("📊 ID Sequence Tracker Initialization Summary:");
            $this->command->info("✅ Successfully initialized: {$successCount} tables");
            if ($errorCount > 0) {
                $this->command->error("❌ Errors encountered: {$errorCount} tables");
            }

            // Clean up orphaned tracker entries
            $deletedCount = IdSequenceService::cleanupOrphanedTrackers();
            if ($deletedCount > 0) {
                $this->command->info("🧹 Cleaned up {$deletedCount} orphaned tracker entries");
            }

        } catch (\Exception $e) {
            $this->command->error("❌ Error during ID Sequence Tracker initialization: " . $e->getMessage());
        }
    }
} 