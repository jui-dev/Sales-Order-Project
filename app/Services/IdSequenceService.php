<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IdSequenceService
{
    /**
     * Get the next ID for a table, ensuring it continues from the last assigned ID
     * and prevents duplicate IDs during simultaneous inserts
     */
    public static function getNextId(string $tableName): int
    {
        // Use cache to prevent race conditions during simultaneous inserts
        $cacheKey = "id_sequence_{$tableName}";
        
        return Cache::lock("lock_{$cacheKey}", 10)->block(5, function () use ($tableName, $cacheKey) {
            try {
                // Get current state from tracker
                $tracker = DB::table('id_sequence_tracker')
                    ->where('table_name', $tableName)
                    ->first();

                if (!$tracker) {
                    // Initialize tracker for this table
                    $currentMaxId = self::getCurrentMaxId($tableName);
                    $tracker = (object) [
                        'last_assigned_id' => $currentMaxId,
                        'current_max_id' => $currentMaxId
                    ];
                    
                    DB::table('id_sequence_tracker')->insert([
                        'table_name' => $tableName,
                        'last_assigned_id' => $tracker->last_assigned_id,
                        'current_max_id' => $tracker->current_max_id,
                        'last_updated' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Calculate next ID
                $nextId = $tracker->last_assigned_id + 1;
                
                // Verify this ID is not already in use
                $existingId = DB::table($tableName)
                    ->where('id', $nextId)
                    ->exists();

                if ($existingId) {
                    // Find the next available ID
                    $nextId = self::findNextAvailableId($tableName, $nextId);
                }

                // Update tracker with new last assigned ID
                DB::table('id_sequence_tracker')
                    ->where('table_name', $tableName)
                    ->update([
                        'last_assigned_id' => $nextId,
                        'current_max_id' => self::getCurrentMaxId($tableName),
                        'last_updated' => now(),
                        'updated_at' => now(),
                    ]);

                // Cache the next ID to prevent immediate duplicates
                Cache::put($cacheKey, $nextId, 60);

                Log::info("Generated next ID for table {$tableName}: {$nextId}");

                return $nextId;

            } catch (\Exception $e) {
                Log::error("Error generating next ID for table {$tableName}: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get the current maximum ID in a table
     */
    public static function getCurrentMaxId(string $tableName): int
    {
        try {
            $result = DB::table($tableName)->max('id');
            return $result ? (int) $result : 0;
        } catch (\Exception $e) {
            Log::error("Error getting max ID for table {$tableName}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Find the next available ID starting from a given ID
     */
    public static function findNextAvailableId(string $tableName, int $startId): int
    {
        $nextId = $startId;
        
        while (DB::table($tableName)->where('id', $nextId)->exists()) {
            $nextId++;
        }
        
        return $nextId;
    }

    /**
     * Reset the ID sequence for a table (when table is empty)
     */
    public static function resetSequence(string $tableName): bool
    {
        try {
            // Check if table is empty
            $count = DB::table($tableName)->count();
            
            if ($count > 0) {
                Log::warning("Cannot reset sequence for table {$tableName}: table is not empty ({$count} records)");
                return false;
            }

            // Reset auto-increment to 1
            DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");

            // Update tracker
            DB::table('id_sequence_tracker')
                ->where('table_name', $tableName)
                ->update([
                    'last_assigned_id' => 0,
                    'current_max_id' => 0,
                    'last_updated' => now(),
                    'updated_at' => now(),
                ]);

            // Clear cache
            Cache::forget("id_sequence_{$tableName}");

            Log::info("Reset ID sequence for table {$tableName}");

            return true;

        } catch (\Exception $e) {
            Log::error("Error resetting sequence for table {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync the tracker with the actual database state
     */
    public static function syncTracker(string $tableName): bool
    {
        try {
            $currentMaxId = self::getCurrentMaxId($tableName);
            
            DB::table('id_sequence_tracker')
                ->updateOrInsert(
                    ['table_name' => $tableName],
                    [
                        'last_assigned_id' => $currentMaxId,
                        'current_max_id' => $currentMaxId,
                        'last_updated' => now(),
                        'updated_at' => now(),
                    ]
                );

            Log::info("Synced tracker for table {$tableName} with max ID: {$currentMaxId}");

            return true;

        } catch (\Exception $e) {
            Log::error("Error syncing tracker for table {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tracker information for a table
     */
    public static function getTrackerInfo(string $tableName): ?array
    {
        try {
            $tracker = DB::table('id_sequence_tracker')
                ->where('table_name', $tableName)
                ->first();

            if (!$tracker) {
                return null;
            }

            $currentMaxId = self::getCurrentMaxId($tableName);

            return [
                'table_name' => $tracker->table_name,
                'last_assigned_id' => $tracker->last_assigned_id,
                'current_max_id' => $tracker->current_max_id,
                'actual_max_id' => $currentMaxId,
                'next_id' => $tracker->last_assigned_id + 1,
                'last_updated' => $tracker->last_updated,
            ];

        } catch (\Exception $e) {
            Log::error("Error getting tracker info for table {$tableName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize tracker for all tables
     */
    public static function initializeAllTrackers(): array
    {
        $results = [];
        
        try {
            $tables = DB::select("SHOW TABLES");
            
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                
                // Skip the tracker table itself
                if ($tableName === 'id_sequence_tracker') {
                    continue;
                }

                $success = self::syncTracker($tableName);
                $results[$tableName] = $success;
            }

            Log::info("Initialized trackers for " . count($results) . " tables");

        } catch (\Exception $e) {
            Log::error("Error initializing trackers: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Clean up orphaned tracker entries
     */
    public static function cleanupOrphanedTrackers(): int
    {
        try {
            $trackers = DB::table('id_sequence_tracker')->get();
            $deletedCount = 0;

            foreach ($trackers as $tracker) {
                $tableExists = DB::select("SHOW TABLES LIKE '{$tracker->table_name}'");
                
                if (empty($tableExists)) {
                    DB::table('id_sequence_tracker')
                        ->where('table_name', $tracker->table_name)
                        ->delete();
                    $deletedCount++;
                }
            }

            Log::info("Cleaned up {$deletedCount} orphaned tracker entries");

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error("Error cleaning up orphaned trackers: " . $e->getMessage());
            return 0;
        }
    }
} 