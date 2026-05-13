<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\Library;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetAuditLogsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $action = $filters['action'] ?: null;
        $libraryId = $filters['library_id'] ?: null;
        $dateFrom = $filters['date_from'] ?: null;
        $dateTo = $filters['date_to'] ?: null;
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));

        return $this->baseQuery()
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->when($action, fn (Builder $query) => $query->where('action', $action))
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $action = $filters['action'] ?: null;
        $libraryId = $filters['library_id'] ?: null;
        $dateFrom = $filters['date_from'] ?: null;
        $dateTo = $filters['date_to'] ?: null;

        $query = $this->baseQuery()
            ->when($search !== '', fn (Builder $builder) => $this->applySearch($builder, $search))
            ->when($action, fn (Builder $builder) => $builder->where('action', $action))
            ->when($libraryId, fn (Builder $builder) => $builder->where('library_id', $libraryId))
            ->when($dateFrom, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $dateTo));

        return [
            'total' => (clone $query)->count(),
            'created' => (clone $query)->where('action', 'like', '%_created')->count(),
            'updated' => (clone $query)->where(function (Builder $builder) {
                $builder
                    ->where('action', 'like', '%_updated')
                    ->orWhere('action', 'like', '%status_changed%')
                    ->orWhere('action', 'like', '%toggled%')
                    ->orWhere('action', 'like', '%fulfilled%');
            })->count(),
            'deleted' => (clone $query)->where(function (Builder $builder) {
                $builder
                    ->where('action', 'like', '%_deleted')
                    ->orWhere('action', 'like', '%cancelled%');
            })->count(),
        ];
    }

    /**
     * @return Collection<int, Library>
     */
    public function libraries(): Collection
    {
        return Library::query()->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function baseQuery(): Builder
    {
        return AuditLog::query()->with(['actor:id,name,email', 'library:id,name,code']);
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $nested) use ($search) {
            $nested->where('description', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('metadata', 'like', "%{$search}%")
                ->orWhereHas('actor', function (Builder $actorQuery) use ($search) {
                    $actorQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('library', function (Builder $libraryQuery) use ($search) {
                    $libraryQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
        });
    }
}








