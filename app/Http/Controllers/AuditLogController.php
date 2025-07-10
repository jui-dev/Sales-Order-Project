<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with(['user'])
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('created_at', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn($q) => $q->whereDate('created_at', '<=', $request->end_date))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->orderByDesc('created_at');

        $auditLogs = $query->paginate(20)->withQueryString();
        $users = class_exists('App\\Models\\User') ? \App\Models\User::orderBy('name')->get() : collect();
        $actions = AuditLog::select('action')->distinct()->pluck('action');

        return view('audit-logs.index', compact('auditLogs', 'users', 'actions'));
    }
} 