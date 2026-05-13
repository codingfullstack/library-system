<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageAuthorRequest;
use App\Models\Author;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForAuthorQuery;
use App\Queries\Management\Authors\GenerateUniqueAuthorSlugQuery;
use App\Queries\Management\Authors\GetManageAuthorsQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function index(Request $request, GetManageAuthorsQuery $getManageAuthorsQuery): View
    {
        return view('manage.authors.index', [
            'authors' => $getManageAuthorsQuery->handle(trim((string) $request->query('search', ''))),
        ]);
    }

    public function create(): View
    {
        return view('manage.authors.create', [
            'author' => new Author(),
        ]);
    }

    public function store(
        ManageAuthorRequest $request,
        GenerateUniqueAuthorSlugQuery $generateUniqueAuthorSlugQuery
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['slug'] = $generateUniqueAuthorSlugQuery->handle(
            $validated['slug'] ?: Str::slug($validated['name'])
        );

        $author = Author::create($validated);

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'author_created',
            $author,
            sprintf('Sukurtas autorius "%s".', $author->name),
            [
                'author_name' => $author->name,
                'slug' => $author->slug,
            ]
        );

        return redirect()
            ->route('manage.authors.index')
            ->with('success', 'Autorius sukurtas.');
    }

    public function edit(
        Request $request,
        Author $author,
        GetRecentAuditLogsForAuthorQuery $getRecentAuditLogsForAuthorQuery
    ): View {
        return view('manage.authors.edit', [
            'author' => $author,
            'auditLogs' => $request->user()?->isSuperAdmin()
                ? $getRecentAuditLogsForAuthorQuery->handle($author)
                : collect(),
        ]);
    }

    public function update(
        ManageAuthorRequest $request,
        Author $author,
        GenerateUniqueAuthorSlugQuery $generateUniqueAuthorSlugQuery
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['slug'] = $generateUniqueAuthorSlugQuery->handle(
            $validated['slug'] ?: Str::slug($validated['name']),
            $author->id
        );

        $author->fill($validated);
        $changedFields = array_keys($author->getDirty());
        $changeSummary = AuditLogChanges::fromModel($author, $changedFields);
        $author->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'author_updated',
            $author,
            sprintf('Atnaujintas autorius "%s".', $author->name),
            array_merge([
                'author_name' => $author->name,
            ], $changeSummary)
        );

        return redirect()
            ->route('manage.authors.index')
            ->with('success', 'Autorius atnaujintas.');
    }

    public function destroy(Request $request, Author $author): RedirectResponse
    {
        if ($author->books()->exists()) {
            return back()->with('error', 'Autoriaus ištrinti negalima, nes jis naudojamas knygose.');
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'author_deleted',
            $author,
            sprintf('Ištrintas autorius "%s".', $author->name),
            [
                'author_name' => $author->name,
                'snapshot' => [
                    'name' => $author->name,
                    'slug' => $author->slug,
                    'bio' => $author->bio,
                ],
            ]
        );

        $author->delete();

        return redirect()
            ->route('manage.authors.index')
            ->with('success', 'Autorius ištrintas.');
    }
}








