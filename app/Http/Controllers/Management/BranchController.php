<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBranchRequest;
use App\Models\Branch;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForBranchQuery;
use App\Queries\Management\Branches\GetManageBranchFormDataQuery;
use App\Queries\Management\Branches\GetManageBranchesQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request, GetManageBranchesQuery $getManageBranchesQuery): View
    {
        return view('manage.branches.index', [
            'branches' => $getManageBranchesQuery->handle(
                $request->user(),
                trim((string) $request->query('search', ''))
            ),
        ]);
    }

    public function create(Request $request, GetManageBranchFormDataQuery $getManageBranchFormDataQuery): View
    {
        return view('manage.branches.create', $getManageBranchFormDataQuery->handle($request->user(), new Branch()));
    }

    public function store(ManageBranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($this->payload($request));

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'branch_created',
            $branch,
            sprintf('Sukurtas filialas "%s".', $branch->name),
            ['branch_name' => $branch->name],
            $branch->library_id
        );

        return redirect()
            ->route('manage.branches.index')
            ->with('success', 'Filialas sukurtas.');
    }

    public function edit(
        Request $request,
        Branch $branch,
        GetManageBranchFormDataQuery $getManageBranchFormDataQuery,
        GetRecentAuditLogsForBranchQuery $getRecentAuditLogsForBranchQuery
    ): View
    {
        $this->ensureVisible($request, $branch);

        return view('manage.branches.edit', array_merge(
            $getManageBranchFormDataQuery->handle($request->user(), $branch),
            [
                'auditLogs' => $request->user()?->isSuperAdmin()
                    ? $getRecentAuditLogsForBranchQuery->handle($branch)
                    : collect(),
            ]
        ));
    }

    public function update(ManageBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->ensureVisible($request, $branch);
        $branch->fill($this->payload($request));
        $changedFields = array_keys($branch->getDirty());
        $changeSummary = AuditLogChanges::fromModel($branch, $changedFields);
        $branch->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'branch_updated',
            $branch,
            sprintf('Atnaujintas filialas "%s".', $branch->name),
            array_merge([
                'branch_name' => $branch->name,
            ], $changeSummary),
            $branch->library_id
        );

        return redirect()
            ->route('manage.branches.index')
            ->with('success', 'Filialas atnaujintas.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->ensureVisible($request, $branch);

        if ($branch->locations()->exists()) {
            return back()->with('error', 'Filialo ištrinti negalima, nes jis turi vietų.');
        }

        if ($branch->bookCopies()->exists()) {
            return back()->with('error', 'Filialo ištrinti negalima, nes jis turi kopijų.');
        }

        $branch->loadMissing('library:id,name');

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'branch_deleted',
            $branch,
            sprintf('Ištrintas filialas "%s".', $branch->name),
            [
                'branch_name' => $branch->name,
                'snapshot' => [
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'address' => $branch->address,
                    'city' => $branch->city,
                    'library' => $branch->library?->name,
                ],
            ],
            $branch->library_id
        );

        $branch->delete();

        return redirect()
            ->route('manage.branches.index')
            ->with('success', 'Filialas ištrintas.');
    }

    private function payload(ManageBranchRequest $request): array
    {
        return [
            'library_id' => $request->user()->isSuperAdmin()
                ? $request->integer('library_id')
                : $request->user()->activeLibraryId(),
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'address' => $request->validated('address'),
            'city' => $request->validated('city'),
        ];
    }

    private function ensureVisible(Request $request, Branch $branch): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        abort_unless($branch->library_id === $request->user()->activeLibraryId(), 404);
    }
}








