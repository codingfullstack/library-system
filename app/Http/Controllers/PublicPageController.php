<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\SchemaCollection;

class PublicPageController extends Controller
{
    public function home(SeoService $seoService): View
    {
        return view('welcome', [
            'publicStats' => $this->publicStats(),
            'seo' => $seoService->make(
                title: 'Bibliotekų valdymo sistema',
                description: 'Moderni bibliotekų valdymo sistema bibliotekoms, darbuotojams ir skaitytojams.',
                canonicalUrl: route('home'),
                image: $this->pageImage('home'),
                schema: $this->publicSchema(['Bibliotekų sistema' => route('home')]),
            ),
        ]);
    }

    public function about(SeoService $seoService): View
    {
        return view('about', [
            'publicStats' => $this->publicStats(),
            'seo' => $seoService->make(
                title: 'Apie bibliotekų sistemą',
                description: 'Sužinokite apie bibliotekų valdymo sistemą, jos galimybes ir naudą bibliotekoms bei skaitytojams.',
                canonicalUrl: route('about'),
                image: $this->pageImage('about'),
                schema: $this->publicSchema([
                    'Bibliotekų sistema' => route('home'),
                    'Apie' => route('about'),
                ]),
            ),
        ]);
    }

    public function libraries(SeoService $seoService): View
    {
        $libraries = Library::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'code', 'address', 'city']);

        return view('libraries.index', [
            'libraries' => $libraries,
            'seo' => $seoService->make(
                title: 'Viešų bibliotekų sąrašas',
                description: 'Peržiūrėkite viešų bibliotekų sąrašą, raskite biblioteką ir sužinokite pagrindinę jos informaciją.',
                canonicalUrl: route('public.libraries.index'),
                image: $this->pageImage('libraries'),
                schema: $this->publicSchema([
                    'Bibliotekų sistema' => route('home'),
                    'Bibliotekos' => route('public.libraries.index'),
                ], $libraries),
            ),
        ]);
    }

    public function library(Library $library, SeoService $seoService): View
    {
        abort_unless($library->is_active && $library->is_public, 404);

        $description = collect([$library->city, $library->address, $library->name])
            ->filter()
            ->join(', ');

        return view('libraries.show', [
            'library' => $library->loadCount(['bookCopies', 'memberships']),
            'seo' => $seoService->make(
                title: $library->name,
                description: $description ?: 'Viešos bibliotekos informacija bibliotekų sistemoje.',
                canonicalUrl: route('public.libraries.show', $library),
                image: $this->pageImage('libraries'),
                robots: 'noindex,nofollow',
                schema: $this->publicSchema([
                    'Bibliotekų sistema' => route('home'),
                    'Bibliotekos' => route('public.libraries.index'),
                    $library->name => route('public.libraries.show', $library),
                ], collect([$library])),
            ),
        ]);
    }

    public function contact(SeoService $seoService): View
    {
        return view('public.contact', [
            'seo' => $seoService->make(
                title: 'Kontaktai ir susisiekimas',
                description: 'Susisiekite dėl sistemos naudojimo, pagalbos ar papildomos informacijos.',
                canonicalUrl: route('contacts'),
                image: $this->pageImage('contact'),
                schema: $this->publicSchema([
                    'Bibliotekų sistema' => route('home'),
                    'Kontaktai' => route('contacts'),
                ]),
            ),
        ]);
    }

    public function help(SeoService $seoService): View
    {
        $topics = [
            ['icon' => 'magnifying-glass', 'title' => 'Knygos paieška', 'text' => 'Kataloge ieškokite pagal pavadinimą, autorių, kategoriją ar kitus filtrus.'],
            ['icon' => 'calendar-days', 'title' => 'Rezervacijos', 'text' => 'Prisijungę nariai gali rezervuoti knygas ir sekti savo rezervacijų būsenas.'],
            ['icon' => 'book-open-text', 'title' => 'Išduotos knygos', 'text' => 'Nario paskyroje matysite šiuo metu išduotas knygas, terminus ir istoriją.'],
            ['icon' => 'bell', 'title' => 'Pranešimai', 'text' => 'Sistema informuoja apie rezervacijų pokyčius, vėlavimus ir svarbius veiksmus.'],
        ];

        $faq = [
            ['question' => 'Kodėl nematau katalogo?', 'answer' => 'Katalogas šioje sistemoje pasiekiamas prisijungusiems naudotojams, nes matomumas priklauso nuo bibliotekos ir rolės teisių.'],
            ['question' => 'Kas gali išduoti arba grąžinti knygą?', 'answer' => 'Šiuos veiksmus atlieka darbuotojai, administratoriai arba superadministratoriai pagal savo bibliotekos teises.'],
            ['question' => 'Kaip veikia rezervacijų eilė?', 'answer' => 'Rezervacija priklauso knygai. Kai atsiranda laisvas egzempliorius, sistema gali aptarnauti eilę pagal aktyvias rezervacijas.'],
            ['question' => 'Kur kreiptis dėl paskyros?', 'answer' => 'Dėl paskyros duomenų, bibliotekos priskyrimo ar rolės pakeitimo kreipkitės į savo bibliotekos administratorių.'],
        ];

        return view('help', [
            'topics' => $topics,
            'faq' => $faq,
            'seo' => $seoService->make(
                title: 'Pagalba vartotojams',
                description: 'Raskite atsakymus į dažniausiai užduodamus klausimus ir naudojimosi sistema instrukcijas.',
                canonicalUrl: route('help'),
                image: $this->pageImage('help'),
                schema: $this->helpSchema($faq),
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

    /**
     * @param  array<string, string>  $items
     */
    private function publicSchema(array $items, iterable $libraries = []): SchemaCollection
    {
        $schema = SchemaCollection::initialize()
            ->push($this->organizationSchema())
            ->push($this->websiteSchema())
            ->addBreadcrumbs(fn ($schema) => $schema->appendBreadcrumbs($items));

        $librarySchema = $this->librarySchema($libraries);

        if ($librarySchema !== null) {
            $schema->push($librarySchema);
        }

        return $schema;
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faq
     */
    private function helpSchema(array $faq): SchemaCollection
    {
        return $this->publicSchema([
            'Bibliotekų sistema' => route('home'),
            'Pagalba' => route('help'),
        ])->addFaqPage(function ($schema) use ($faq) {
            foreach ($faq as $item) {
                $schema->addQuestion($item['question'], $item['answer']);
            }

            return $schema;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => route('home').'#organization',
            'name' => (string) config('seo.site_name', 'Bibliotekų sistema'),
            'url' => route('home'),
            'logo' => secure_url('favicon.svg'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => route('home').'#website',
            'name' => (string) config('seo.site_name', 'Bibliotekų sistema'),
            'url' => route('home'),
            'publisher' => [
                '@id' => route('home').'#organization',
            ],
            'inLanguage' => 'lt-LT',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function librarySchema(iterable $libraries): ?array
    {
        $items = collect($libraries)
            ->values()
            ->map(fn (Library $library, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'Library',
                    '@id' => route('public.libraries.show', $library).'#library',
                    'name' => $library->name,
                    'url' => route('public.libraries.show', $library),
                    'address' => collect([$library->address, $library->city])->filter()->join(', ') ?: null,
                    'identifier' => $library->code,
                ],
            ])
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            '@id' => route('public.libraries.index').'#libraries',
            'name' => 'Viešų bibliotekų sąrašas',
            'itemListElement' => $items,
        ];
    }

    private function pageImage(string $page): ?string
    {
        return null;
    }
}
