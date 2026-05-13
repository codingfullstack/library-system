<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Queries\Management\AuditLogs\GetAuditLogsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, GetAuditLogsQuery $getAuditLogsQuery): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'action' => $request->query('action'),
            'library_id' => $request->query('library_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'per_page' => $request->query('per_page', 25),
        ];

        return view('manage.audit-logs.index', [
            'auditLogs' => $getAuditLogsQuery->handle($filters),
            'summary' => $getAuditLogsQuery->summary($filters),
            'libraries' => $getAuditLogsQuery->libraries(),
        ]);
    }
}








