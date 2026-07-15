<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Queries\Books\GetBookCopyFiltersDataQuery;
use App\Queries\Books\GetBookIndexFiltersDataQuery;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use App\Queries\Books\GetLibraryBooksQuery;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForBookQuery;
use App\Services\ReservationQueueDebugService;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class BookController extends Controller
{
    public function index(
        Request $request,
        GetLibraryBooksQuery $getLibraryBooksQuery,
        GetBookIndexFiltersDataQuery $getBookIndexFiltersDataQuery,
    ): View {
        $actor = $request->user();
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'author_id' => $request->query('author_id'),
            'publisher_id' => $request->query('publisher_id'),
            'library_id' => $request->query('library_id'),
            'branch_id' => $request->query('branch_id'),
            'availability' => $request->query('availability'),
            'sort' => $request->query('sort', 'title'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => $request->query('per_page', 15),
        ];
        $books = $getLibraryBooksQuery->handle($actor, $filters);

        return view($actor->effectiveRole() === 'narys' ? 'account.books.index' : 'books.index', array_merge(
            ['books' => $books],
            $getBookIndexFiltersDataQuery->handle($actor, $filters)
        ));
    }

    public function show(
        Request $request,
        Book $book,
        GetLibraryBookDetailsQuery $getLibraryBookDetailsQuery,
        GetBookCopyFiltersDataQuery $getBookCopyFiltersDataQuery,
        GetRecentAuditLogsForBookQuery $getRecentAuditLogsForBookQuery,
        ReservationQueueDebugService $reservationQueueDebugService,
        SeoService $seoService,
    ): View {
        $actor = $request->user();
        $book = $getLibraryBookDetailsQuery->handle($actor, $book, [
            'copy_search' => $request->query('copy_search'),
            'copy_lifecycle' => $request->query('copy_lifecycle'),
            'copy_status' => $request->query('copy_status'),
            'branch_id' => $request->query('branch_id'),
            'location_id' => $request->query('location_id'),
        ]);

        if ($actor->effectiveRole() === 'narys') {
            $currentReservation = $book->reservations
                ->filter(fn ($reservation) => $reservation->isPending())
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->first();

            $memberReservation = $book->reservations
                ->first(fn ($reservation) => (int) $reservation->user_id === (int) $actor->id && $reservation->isPending());

            return view('account.books.show', [
                'book' => $book,
                'memberReservation' => $memberReservation,
                'currentReservation' => $currentReservation,
                'seo' => $this->bookSeo($book, $seoService),
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
                'reservationQueueDebug' => $reservationQueueDebugService->forBook($book, $actor),
                'seo' => $this->bookSeo($book, $seoService),
            ],
            $getBookCopyFiltersDataQuery->handle($request->user(), $book)
        ));
    }

    private function bookSeo(Book $book, SeoService $seoService): SEOData
    {
        $description = filled($book->description)
            ? Str::limit(strip_tags((string) $book->description), 155, '')
            : collect([
                $book->authors->pluck('name')->join(', '),
                $book->publisher?->name,
                $book->publication_year,
                $book->categories->pluck('name')->join(', '),
            ])->filter()->join(', ');

        return $seoService->make(
            title: $book->title,
            description: $description ?: 'Knygos informacija bibliotekų sistemoje.',
            canonicalUrl: route('books.show', $book),
            image: $book->cover_image_url,
            robots: 'noindex,nofollow',
        );
    }
}
