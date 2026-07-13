<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageLocationRequest;
use App\Models\Location;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForLocationQuery;
use App\Queries\Management\Locations\GetManageLocationFormDataQuery;
use App\Queries\Management\Locations\GetManageLocationsQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request, GetManageLocationsQuery $getManageLocationsQuery): View
    {
        return view('manage.locations.index', [
            'locations' => $getManageLocationsQuery->handle(
                $request->user(),
                trim((string) $request->query('search', ''))
            ),
        ]);
    }

    public function create(Request $request, GetManageLocationFormDataQuery $getManageLocationFormDataQuery): View
    {
        return view('manage.locations.create', $getManageLocationFormDataQuery->handle($request->user(), new Location()));
    }

    public function store(ManageLocationRequest $request): RedirectResponse
    {
        $location = Location::create($this->payload($request));

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'location_created',
            $location,
            sprintf('Sukurta vieta "%s".', $location->name),
            ['location_name' => $location->name],
            $location->library_id
        );

        return redirect()
            ->route('manage.locations.index')
            ->with('success', 'Vieta sukurta.');
    }

    public function edit(
        Request $request,
        Location $location,
        GetManageLocationFormDataQuery $getManageLocationFormDataQuery,
        GetRecentAuditLogsForLocationQuery $getRecentAuditLogsForLocationQuery
    ): View
    {
        $this->ensureVisible($request, $location);

        return view('manage.locations.edit', array_merge(
            $getManageLocationFormDataQuery->handle($request->user(), $location),
            [
                'auditLogs' => $request->user()?->isSuperAdmin()
                    ? $getRecentAuditLogsForLocationQuery->handle($location)
                    : collect(),
            ]
        ));
    }

    public function update(ManageLocationRequest $request, Location $location): RedirectResponse
    {
        $this->ensureVisible($request, $location);
        $location->fill($this->payload($request));
        $changedFields = array_keys($location->getDirty());
        $changeSummary = AuditLogChanges::fromModel($location, $changedFields);
        $location->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'location_updated',
            $location,
            sprintf('Atnaujinta vieta "%s".', $location->name),
            array_merge([
                'location_name' => $location->name,
            ], $changeSummary),
            $location->library_id
        );

        return redirect()
            ->route('manage.locations.index')
            ->with('success', 'Vieta atnaujinta.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->ensureVisible($request, $location);

        if ($location->bookCopies()->exists()) {
            return back()->with('error', 'Vietos ištrinti negalima, nes ji naudojama kopijose.');
        }

        $location->loadMissing('branch:id,name');

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'location_deleted',
            $location,
            sprintf('Ištrinta vieta "%s".', $location->name),
            [
                'location_name' => $location->name,
                'snapshot' => [
                    'name' => $location->name,
                    'code' => $location->code,
                    'room' => $location->room,
                    'shelf' => $location->shelf,
                    'description' => $location->description,
                    'branch' => $location->branch?->name,
                ],
            ],
            $location->library_id
        );

        $location->delete();

        return redirect()
            ->route('manage.locations.index')
            ->with('success', 'Vieta ištrinta.');
    }

    private function payload(ManageLocationRequest $request): array
    {
        return [
            'library_id' => $request->user()->isSuperAdmin()
                ? $request->integer('library_id')
                : $request->user()->activeLibraryId(),
            'branch_id' => $request->integer('branch_id'),
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'room' => $request->validated('room'),
            'shelf' => $request->validated('shelf'),
            'description' => $request->validated('description'),
        ];
    }

    private function ensureVisible(Request $request, Location $location): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        abort_unless($location->library_id === $request->user()->activeLibraryId(), 404);
    }
}








