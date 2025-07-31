<?php

namespace App\Services;

use App\Models\PickingList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PickingListService
{
    /**
     * Get all picking lists with pagination
     */
    public function getAllPickingLists(int $perPage = 20): LengthAwarePaginator
    {
        return PickingList::with(['items.product'])
            ->latest('picking_date')
            ->paginate($perPage);
    }

    /**
     * Get picking list with all related data
     */
    public function getPickingListWithDetails(int $id): PickingList
    {
        return PickingList::with([
            'items.product',
            'fromLocation',
            'toLocation',
            'order',
            'supply'
        ])->findOrFail($id);
    }

    /**
     * Get picking list statistics
     */
    public function getPickingListStatistics(): array
    {
        $totalLists = PickingList::count();
        $pendingLists = PickingList::where('status', 'pending')->count();
        $completedLists = PickingList::where('status', 'completed')->count();
        $inProgressLists = PickingList::whereIn('status', ['processing', 'in_progress'])->count();

        return [
            'total_lists' => $totalLists,
            'pending_lists' => $pendingLists,
            'completed_lists' => $completedLists,
            'in_progress_lists' => $inProgressLists,
        ];
    }

    /**
     * Get picking lists by type
     */
    public function getPickingListsByType(string $fromLocationType, string $toLocationType): Collection
    {
        return PickingList::with(['fromLocation', 'toLocation', 'items', 'order'])
            ->where('from_location_type', $fromLocationType)
            ->where('to_location_type', $toLocationType)
            ->latest('picking_date')
            ->get();
    }

    /**
     * Get pending picking lists
     */
    public function getPendingPickingLists(): Collection
    {
        return PickingList::with(['fromLocation', 'toLocation', 'items'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get picking lists for a specific location
     */
    public function getPickingListsForLocation(string $locationType, int $locationId): Collection
    {
        return PickingList::with(['items.product'])
            ->where(function ($query) use ($locationType, $locationId) {
                $query->where('from_location_type', $locationType)
                      ->where('from_location_id', $locationId)
                      ->orWhere('to_location_type', $locationType)
                      ->where('to_location_id', $locationId);
            })
            ->latest('picking_date')
            ->get();
    }
} 