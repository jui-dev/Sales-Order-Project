<?php

namespace App\Models\Traits;

use App\Services\IdSequenceService;
use Illuminate\Database\Eloquent\Model;

trait HasIdSequence
{
    /**
     * Boot the trait
     */
    protected static function bootHasIdSequence()
    {
        static::creating(function (Model $model) {
            // Only set ID if it's not already set
            if (!$model->id) {
                $tableName = $model->getTable();
                $model->id = IdSequenceService::getNextId($tableName);
            }
        });
    }

    /**
     * Get the next ID for this model's table
     */
    public static function getNextId(): int
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::getNextId($tableName);
    }

    /**
     * Reset the ID sequence for this model's table (only if table is empty)
     */
    public static function resetIdSequence(): bool
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::resetSequence($tableName);
    }

    /**
     * Sync the ID tracker for this model's table
     */
    public static function syncIdTracker(): bool
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::syncTracker($tableName);
    }

    /**
     * Get ID tracker information for this model's table
     */
    public static function getIdTrackerInfo(): ?array
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::getTrackerInfo($tableName);
    }

    /**
     * Check if the table is empty
     */
    public static function isTableEmpty(): bool
    {
        return static::count() === 0;
    }

    /**
     * Get the current maximum ID in the table
     */
    public static function getCurrentMaxId(): int
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::getCurrentMaxId($tableName);
    }

    /**
     * Find the next available ID starting from a given ID
     */
    public static function findNextAvailableId(int $startId): int
    {
        $tableName = (new static)->getTable();
        return IdSequenceService::findNextAvailableId($tableName, $startId);
    }
} 