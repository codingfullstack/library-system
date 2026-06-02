<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Http\Controllers\Controller;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForBookQuery;
use App\Queries\Books\GetBookCopyFiltersDataQuery;
use App\Queries\Books\GetBookIndexFiltersDataQuery;
use App\Queries\Books\GetLibraryBooksQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use App\Services\SeoService;
use Illuminate\Pagination\LengthAwarePaginator;

class BookController extends Controller
{
    public function index(
        Request $request,
        GetLibraryBooksQuery $getLibraryBooksQuery,
        GetBookIndexFiltersDataQuery $getBookIndexFiltersDataQuery,
        SeoService $seoService,
    ): View
    {
        $actor = $request->user();
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'author_id' => $request->query('author_id'),
            'publisher_id' => $request->query('publisher_id'),
            'library_id' => $request->query('library_id'),
            'availability' => $request->query('availability'),
            'sort' => $request->query('sort', 'title'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => $request->query('per_page', 15),
        ];
        $books = $getLibraryBooksQuery->handle($actor, $filters);

        if ($actor === null) {
            return view('public.books.index', array_merge(
                [
                    'books' => $books,
                    'seo' => $seoService->make(
                        title: 'Knygu katalogas',
                        description: 'Viesas biblioteku knygu katalogas su paieska pagal pavadinima, autoriu, kategorija ir ISBN.'
                    ),
                ],
                $getBookIndexFiltersDataQuery->handle(null)
            ));
        }

        return view($actor->effectiveRole() === 'narys' ? 'account.books.index' : 'books.index', array_merge(
            ['books' => $books],
            $getBookIndexFiltersDataQuery->handle($actor)
        ));
    }

    public function show(
        Request $request,
        Book $book,
        GetLibraryBookDetailsQuery $getLibraryBookDetailsQuery,
        GetBookCopyFiltersDataQuery $getBookCopyFiltersDataQuery,
        GetRecentAuditLogsForBookQuery $getRecentAuditLogsForBookQuery,
        SeoService $seoService,
    ): View
    {
        $actor = $request->user();
        $book = $getLibraryBookDetailsQuery->handle($actor, $book, [
            'copy_lifecycle' => $request->query('copy_lifecycle'),
            'copy_status' => $request->query('copy_status'),
            'branch_id' => $request->query('branch_id'),
            'location_id' => $request->query('location_id'),
        ]);

        if ($actor === null) {
            return view('public.books.show', [
                'book' => $book,
                'seo' => $seoService->make(
                    title: $book->title,
                    description: $book->description ?: 'Knygos informacija, autoriai, kategorijos ir prieinamumas viesose bibliotekose.',
                    type: 'article',
                ),
            ]);
        }

        if ($actor->effectiveRole() === 'narys') {
            $currentReservation = $book->reservations
                ->filter(fn ($reservation) => $reservation->isPending())
                ->sortBy('reserved_at')
                ->first();

            $memberReservation = $book->reservations
                ->first(fn ($reservation) => (int) $reservation->user_id === (int) $actor->id && $reservation->isPending());

            return view('account.books.show', [
                'book' => $book,
                'memberReservation' => $memberReservation,
                'currentReservation' => $currentReservation,
            ]);
        }

        $copyPage = max((int) $request->query('copy-page', 1), 1);
        $copyPerPage = 10;
        $filteredBookCopies = $book->bookCopies->values();
        $bookCopies = new LengthAwarePaginator(
            $filteredBookCopies->forPage($copyPage, $copyPerPage)->values(),
            $filteredBookCopies->count(),
            $copyPerPage,
            $copyPage,
            [
                'path' => $request->url(),
                'pageName' => 'copy-page',
            ]
        );

        return view('books.show', array_merge(
            [
                'book' => $book,
                'bookCopies' => $bookCopies,
                'auditLogs' => $actor?->isSuperAdmin()
                    ? $getRecentAuditLogsForBookQuery->handle($book)
                    : collect(),
            ],
            $getBookCopyFiltersDataQuery->handle($request->user(), $book)
        ));
    }
}








