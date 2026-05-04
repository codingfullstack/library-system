<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRecentAuditLogsForModelQuery
{
    public function handle(Model $model, int $perPage = 8, string $pageName = 'audit-page'): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->latest()
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }
}
