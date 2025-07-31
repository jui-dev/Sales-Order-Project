<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AuditLogService
{
    /**
     * Get filtered audit logs with pagination
     */
    public function getFilteredAuditLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::with(['user']);

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Get filter options for the view
     */
    public function getFilterOptions(): array
    {
        $users = class_exists('App\\Models\\User') 
            ? \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray() 
            : [];

        $actions = AuditLog::select('action')->distinct()->pluck('action')->toArray();
        $actionOptions = array_combine($actions, $actions);

        return [
            'action' => [
                'type' => 'select',
                'label' => 'Action',
                'options' => $actionOptions
            ],
            'user_id' => [
                'type' => 'select',
                'label' => 'User',
                'options' => $users
            ],
            'start_date' => [
                'type' => 'date',
                'label' => 'Start Date',
                'placeholder' => 'Select start date...'
            ],
            'end_date' => [
                'type' => 'date',
                'label' => 'End Date',
                'placeholder' => 'Select end date...'
            ]
        ];
    }

    /**
     * Get audit log statistics
     */
    public function getAuditLogStatistics(): array
    {
        $totalLogs = AuditLog::count();
        $todayLogs = AuditLog::whereDate('created_at', today())->count();
        $thisWeekLogs = AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $thisMonthLogs = AuditLog::whereMonth('created_at', now()->month)->count();

        return [
            'total_logs' => $totalLogs,
            'today_logs' => $todayLogs,
            'this_week_logs' => $thisWeekLogs,
            'this_month_logs' => $thisMonthLogs,
        ];
    }

    /**
     * Get recent audit logs
     */
    public function getRecentAuditLogs(int $limit = 10): Collection
    {
        return AuditLog::with(['user'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
} 