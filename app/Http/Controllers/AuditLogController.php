<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $auditLogs = AuditLog::query()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(30);

        return view('audit-logs.index', compact('auditLogs'));
    }
}
