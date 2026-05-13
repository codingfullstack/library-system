<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManagePublisherRequest;
use App\Models\Publisher;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForPublisherQuery;
use App\Queries\Management\Publishers\GetManagePublishersQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublisherController extends Controller
{
    public function index(Request $request, GetManagePublishersQuery $getManagePublishersQuery): View
    {
        return view('manage.publishers.index', [
            'publishers' => $getManagePublishersQuery->handle(trim((string) $request->query('search', ''))),
        ]);
    }

    public function create(): View
    {
        return view('manage.publishers.create', [
            'publisher' => new Publisher(),
        ]);
    }

    public function store(ManagePublisherRequest $request): RedirectResponse
    {
        $publisher = Publisher::create($request->validated());

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'publisher_created',
            $publisher,
            sprintf('Sukurta leidykla "%s".', $publisher->name),
            ['publisher_name' => $publisher->name]
        );

        return redirect()
            ->route('manage.publishers.index')
            ->with('success', 'Leidykla sukurta.');
    }

    public function edit(Request $request, Publisher $publisher, GetRecentAuditLogsForPublisherQuery $getRecentAuditLogsForPublisherQuery): View
    {
        return view('manage.publishers.edit', [
            'publisher' => $publisher,
            'auditLogs' => $request->user()?->isSuperAdmin()
                ? $getRecentAuditLogsForPublisherQuery->handle($publisher)
                : collect(),
        ]);
    }

    public function update(ManagePublisherRequest $request, Publisher $publisher): RedirectResponse
    {
        $publisher->fill($request->validated());
        $changedFields = array_keys($publisher->getDirty());
        $changeSummary = AuditLogChanges::fromModel($publisher, $changedFields);
        $publisher->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'publisher_updated',
            $publisher,
            sprintf('Atnaujinta leidykla "%s".', $publisher->name),
            array_merge([
                'publisher_name' => $publisher->name,
            ], $changeSummary)
        );

        return redirect()
            ->route('manage.publishers.index')
            ->with('success', 'Leidykla atnaujinta.');
    }

    public function destroy(Publisher $publisher): RedirectResponse
    {
        if ($publisher->books()->exists()) {
            return back()->with('error', 'Leidyklos ištrinti negalima, nes ji naudojama knygose.');
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'publisher_deleted',
            $publisher,
            sprintf('Ištrinta leidykla "%s".', $publisher->name),
            [
                'publisher_name' => $publisher->name,
                'snapshot' => [
                    'name' => $publisher->name,
                    'country' => $publisher->country,
                ],
            ]
        );

        $publisher->delete();

        return redirect()
            ->route('manage.publishers.index')
            ->with('success', 'Leidykla ištrinta.');
    }
}








