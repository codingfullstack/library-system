<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('welcome', [
            'publicStats' => $this->publicStats(),
        ]);
    }

    public function about(): View
    {
        return view('about', [
            'publicStats' => $this->publicStats(),
        ]);
    }

    public function libraries(): View
    {
        return view('libraries.index', [
            'libraries' => Library::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'address', 'city']),
        ]);
    }

    private function publicStats(): array
    {
        $publicLibraryIds = Library::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->pluck('id');

        return [
            'books' => Book::query()
                ->whereHas('bookCopies', fn ($query) => $query->whereIn('library_id', $publicLibraryIds))
                ->count(),
            'copies' => BookCopy::query()->whereIn('library_id', $publicLibraryIds)->count(),
            'members' => User::query()
                ->where('role', 'narys')
                ->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->whereIn('library_id', $publicLibraryIds)
                    ->where('is_active', true))
                ->distinct('users.id')
                ->count('users.id'),
            'libraries' => $publicLibraryIds->count(),
            'activeReservations' => Reservation::query()
                ->pending()
                ->whereIn('library_id', $publicLibraryIds)
                ->count(),
        ];
    }
}








