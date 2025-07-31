<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'action' => $request->action,
            'user_id' => $request->user_id,
        ];

        $auditLogs = $this->auditLogService->getFilteredAuditLogs($filters, 20);
        $filterOptions = $this->auditLogService->getFilterOptions();
        $statistics = $this->auditLogService->getAuditLogStatistics();

        return view('audit-logs.index', compact('auditLogs', 'filterOptions', 'statistics'));
    }
} 