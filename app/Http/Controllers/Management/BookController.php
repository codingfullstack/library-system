<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBookRequest;
use App\Models\Book;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForBookQuery;
use App\Queries\Management\Books\FindVisibleManagedBookQuery;
use App\Queries\Management\Books\GetManageBookFormDataQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function create(GetManageBookFormDataQuery $getManageBookFormDataQuery): View
    {
        return view('manage.books.create', $getManageBookFormDataQuery->handle(new Book()));
    }

    public function store(ManageBookRequest $request): RedirectResponse
    {
        $book = Book::create($this->payload($request));
        $book->authors()->sync($request->validated('author_ids', []));
        $book->categories()->sync($request->validated('category_ids', []));

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_created',
            $book,
            sprintf('Sukurta knyga "%s".', $book->title),
            [
                'book_title' => $book->title,
                'author_ids' => $request->validated('author_ids', []),
                'category_ids' => $request->validated('category_ids', []),
            ]
        );

        return redirect()
            ->route('books.index')
            ->with('success', 'Knyga sėkmingai sukurta.');
    }

    public function edit(
        Request $request,
        Book $book,
        FindVisibleManagedBookQuery $findVisibleManagedBookQuery,
        GetManageBookFormDataQuery $getManageBookFormDataQuery,
        GetRecentAuditLogsForBookQuery $getRecentAuditLogsForBookQuery
    ): View
    {
        $this->authorizeBookMutation($request);

        $book = $findVisibleManagedBookQuery->handle($request->user(), $book);
        $book->load(['authors:id,name', 'categories:id,name']);

        return view('manage.books.edit', array_merge(
            $getManageBookFormDataQuery->handle($book),
            [
                'auditLogs' => $request->user()?->isSuperAdmin()
                    ? $getRecentAuditLogsForBookQuery->handle($book)
                    : collect(),
            ]
        ));
    }

    public function update(
        ManageBookRequest $request,
        Book $book,
        FindVisibleManagedBookQuery $findVisibleManagedBookQuery
    ): RedirectResponse
    {
        $this->authorizeBookMutation($request);

        $book = $findVisibleManagedBookQuery->handle($request->user(), $book);
        $payload = $this->payload($request);
        $book->loadMissing(['authors:id,name', 'categories:id,name', 'publisher:id,name']);
        $beforeAuthors = $book->authors->pluck('name')->sort()->values()->all();
        $beforeCategories = $book->categories->pluck('name')->sort()->values()->all();
        $book->fill($payload);
        $changedFields = array_keys($book->getDirty());
        $changeSummary = AuditLogChanges::fromModel(
            $book,
            $changedFields,
            ['publisher_id' => 'Leidykla'],
            [
                'publisher_id' => function ($value) {
                    if (! $value) {
                        return '-';
                    }

                    return \App\Models\Publisher::query()->whereKey($value)->value('name') ?: (string) $value;
                },
            ]
        );
        $book->save();

        $authorChanges = $book->authors()->sync($request->validated('author_ids', []));
        $categoryChanges = $book->categories()->sync($request->validated('category_ids', []));
        $book->load(['authors:id,name', 'categories:id,name']);
        $afterAuthors = $book->authors->pluck('name')->sort()->values()->all();
        $afterCategories = $book->categories->pluck('name')->sort()->values()->all();

        if ($authorChanges['attached'] !== [] || $authorChanges['detached'] !== [] || $authorChanges['updated'] !== []) {
            $changedFields[] = 'author_ids';
        }

        if ($categoryChanges['attached'] !== [] || $categoryChanges['detached'] !== [] || $categoryChanges['updated'] !== []) {
            $changedFields[] = 'category_ids';
        }

        $changes = collect($changeSummary['changes'] ?? []);

        if ($beforeAuthors !== $afterAuthors) {
            $changes->push([
                'field' => 'author_ids',
                'label' => 'Autoriai',
                'from' => AuditLogChanges::stringify($beforeAuthors),
                'to' => AuditLogChanges::stringify($afterAuthors),
            ]);
        }

        if ($beforeCategories !== $afterCategories) {
            $changes->push([
                'field' => 'category_ids',
                'label' => 'Kategorijos',
                'from' => AuditLogChanges::stringify($beforeCategories),
                'to' => AuditLogChanges::stringify($afterCategories),
            ]);
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_updated',
            $book,
            sprintf('Atnaujinta knygos "%s" informacija.', $book->title),
            [
                'book_title' => $book->title,
                'changed_fields' => array_values(array_unique($changedFields)),
                'changes' => $changes->values()->all(),
            ]
        );

        return redirect()
            ->route('books.index')
            ->with('success', 'Knygos informacija atnaujinta.');
    }

    public function destroy(
        Request $request,
        Book $book,
        FindVisibleManagedBookQuery $findVisibleManagedBookQuery
    ): RedirectResponse
    {
        $this->authorizeBookMutation($request);

        $book = $findVisibleManagedBookQuery->handle($request->user(), $book);

        if ($book->bookCopies()->exists()) {
            return back()->with('error', 'Knygos ištrinti negalima, nes ji turi kopijų.');
        }

        if ($book->reservations()->exists()) {
            return back()->with('error', 'Knygos ištrinti negalima, nes ji turi rezervacijų istoriją.');
        }

        $book->loadMissing(['authors:id,name', 'categories:id,name', 'publisher:id,name']);
        $book->authors()->detach();
        $book->categories()->detach();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'book_deleted',
            $book,
            sprintf('Ištrinta knyga "%s".', $book->title),
            [
                'book_title' => $book->title,
                'isbn' => $book->isbn,
                'snapshot' => [
                    'title' => $book->title,
                    'subtitle' => $book->subtitle,
                    'isbn' => $book->isbn,
                    'publisher' => $book->publisher?->name,
                    'authors' => $book->authors->pluck('name')->values()->all(),
                    'categories' => $book->categories->pluck('name')->values()->all(),
                    'publication_year' => $book->publication_year,
                    'language' => $book->language,
                ],
            ]
        );

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', 'Knyga ištrinta.');
    }

    private function payload(ManageBookRequest $request): array
    {
        $validated = $request->validated();
        $firstCategoryId = collect($validated['category_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->first();

        return collect($validated)
            ->except(['author_ids', 'category_ids'])
            ->merge([
                'category_id' => $firstCategoryId,
            ])
            ->all();
    }

    private function authorizeBookMutation(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}








