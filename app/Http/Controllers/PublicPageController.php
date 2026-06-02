<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function home(SeoService $seoService): View
    {
        return view('welcome', [
            'publicStats' => $this->publicStats(),
            'seo' => $seoService->make(
                title: 'Bibliotekos sistema',
                description: 'Atraskite viesas bibliotekas, knygas ir valdykite rezervacijas vienoje bibliotekos sistemoje.'
            ),
        ]);
    }

    public function about(SeoService $seoService): View
    {
        return view('about', [
            'publicStats' => $this->publicStats(),
            'seo' => $seoService->make(
                title: 'Apie sistema',
                description: 'Bibliotekos sistema sujungia viesaji kataloga, nariu savitarna ir bibliotekos administravimo procesus.'
            ),
        ]);
    }

    public function libraries(SeoService $seoService): View
    {
        return view('libraries.index', [
            'libraries' => Library::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'address', 'city']),
            'seo' => $seoService->make(
                title: 'Bibliotekos',
                description: 'Viesu biblioteku sarasas su adresais ir prisijungimo galimybe skaitytojams.'
            ),
        ]);
    }

    public function library(Library $library, SeoService $seoService): View
    {
        abort_unless($library->is_active && $library->is_public, 404);

        return view('libraries.show', [
            'library' => $library->loadCount(['bookCopies', 'memberships']),
            'seo' => $seoService->make(
                title: $library->name,
                description: collect([$library->name, $library->address, $library->city])
                    ->filter()
                    ->join(', ')
            ),
        ]);
    }

    public function contact(SeoService $seoService): View
    {
        return view('public.contact', [
            'seo' => $seoService->make(
                title: 'Kontaktai',
                description: 'Susisiekite del bibliotekos sistemos, viesu biblioteku ir katalogo informacijos.'
            ),
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








