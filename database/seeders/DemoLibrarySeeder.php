<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCopyStatusHistory;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\ScanLog;
use App\Models\User;
use App\Support\GeneratesSlugs;
use App\Support\Notifications\NotificationType;
use App\Support\Notifications\NotificationUiConfig;
use App\Support\UserManagement;
use Database\Seeders\Support\DemoAccessActorSynchronizer;
use Database\Seeders\Support\GuardsDemoSeeding;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLibrarySeeder extends Seeder
{
    use GuardsDemoSeeding;

    public function run(): void
    {
        $this->guardDemoSeedingIsAllowed();

        DB::transaction(function () {
            $libraryX = Library::query()->updateOrCreate(
                ['code' => 'LIB-X'],
                [
                    'name' => 'Vilniaus miesto centrine biblioteka',
                    'email' => 'centras@library.test',
                    'phone' => '+37060000001',
                    'address' => 'Gedimino pr. 12',
                    'city' => 'Vilnius',
                    'is_active' => true,
                    'is_public' => true,
                ]
            );

            $libraryY = Library::query()->updateOrCreate(
                ['code' => 'LIB-Y'],
                [
                    'name' => 'Kauno rajono viesoji biblioteka',
                    'email' => 'kaunas@library.test',
                    'phone' => '+37060000002',
                    'address' => 'Laisves al. 48',
                    'city' => 'Kaunas',
                    'is_active' => true,
                    'is_public' => true,
                ]
            );

            (new DemoAccessActorSynchronizer())->syncSuperadmins();

            $memberProfilesX = [
                'members' => [
                    ['name' => 'Austeja Kazlauskaite', 'email' => 'austeja.kazlauskaite@example.com', 'phone' => '+37061234001'],
                    ['name' => 'Mantas Balsevicius', 'email' => 'mantas.balsevicius@example.com', 'phone' => '+37061234002'],
                    ['name' => 'Egle Petrauskaite', 'email' => 'egle.petrauskaite@example.com', 'phone' => '+37061234003'],
                    ['name' => 'Lukas Vaitiekunas', 'email' => 'lukas.vaitiekunas@example.com', 'phone' => '+37061234004'],
                    ['name' => 'Saule Grigaityte', 'email' => 'saule.grigaityte@example.com', 'phone' => '+37061234005'],
                    ['name' => 'Rokas Jankauskas', 'email' => 'rokas.jankauskas@example.com', 'phone' => '+37061234006'],
                    ['name' => 'Gabija Rimkutė', 'email' => 'gabija.rimkute@example.com', 'phone' => '+37061234007'],
                    ['name' => 'Emilija Varnyte', 'email' => 'emilija.varnyte@example.com', 'phone' => '+37061234008'],
                    ['name' => 'Nojus Pocius', 'email' => 'nojus.pocius@example.com', 'phone' => '+37061234009'],
                    ['name' => 'Milda Janusauskaite', 'email' => 'milda.janusauskaite@example.com', 'phone' => '+37061234010'],
                    ['name' => 'Tadas Veverskis', 'email' => 'tadas.veverskis@example.com', 'phone' => '+37061234011'],
                    ['name' => 'Karolina Butkevičiūtė', 'email' => 'karolina.butkeviciute@example.com', 'phone' => '+37061234012'],
                    ['name' => 'Simona Petratyte', 'email' => 'simona.petratyte@example.com', 'phone' => '+37061234013'],
                    ['name' => 'Giedre Valentiene', 'email' => 'giedre.valentiene@example.com', 'phone' => '+37061234014'],
                    ['name' => 'Tomas Vaiktus', 'email' => 'tomas.vaiktus@example.com', 'phone' => '+37061234015'],
                    ['name' => 'Aiste Jakaite', 'email' => 'aiste.jakaite@example.com', 'phone' => '+37061234016'],
                    ['name' => 'Urte Zukaite', 'email' => 'urte.zukaite@example.com', 'phone' => '+37061234017'],
                    ['name' => 'Dovile Kairiene', 'email' => 'dovile.kairiene@example.com', 'phone' => '+37061234018'],
                    ['name' => 'Milda Gerdvilaite', 'email' => 'milda.gerdvilaite@example.com', 'phone' => '+37061234019'],
                    ['name' => 'Povilas Morkunas', 'email' => 'povilas.morkunas@example.com', 'phone' => '+37061234020'],
                ],
            ];

            $memberProfilesY = [
                'members' => [
                    ['name' => 'Ieva Noreikaite', 'email' => 'ieva.noreikaite@example.com', 'phone' => '+37061235001'],
                    ['name' => 'Domas Vasiliauskas', 'email' => 'domas.vasiliauskas@example.com', 'phone' => '+37061235002'],
                    ['name' => 'Goda Lukoceviciute', 'email' => 'goda.lukoceviciute@example.com', 'phone' => '+37061235003'],
                    ['name' => 'Ugnius Narbutas', 'email' => 'ugnius.narbutas@example.com', 'phone' => '+37061235004'],
                    ['name' => 'Vakare Simonaityte', 'email' => 'vakare.simonaityte@example.com', 'phone' => '+37061235005'],
                    ['name' => 'Jonas Petraitis', 'email' => 'jonas.petraitis@example.com', 'phone' => '+37061235006'],
                    ['name' => 'Aistė Maciulytė', 'email' => 'aiste.maciulyte@example.com', 'phone' => '+37061235007'],
                    ['name' => 'Pijus Zabiela', 'email' => 'pijus.zabiela@example.com', 'phone' => '+37061235008'],
                    ['name' => 'Greta Šimkutė', 'email' => 'greta.simkute@example.com', 'phone' => '+37061235009'],
                    ['name' => 'Nedas Petrauskas', 'email' => 'nedas.petrauskas@example.com', 'phone' => '+37061235010'],
                    ['name' => 'Paulina Stankute', 'email' => 'paulina.stankute@example.com', 'phone' => '+37061235011'],
                    ['name' => 'Rugile Plioplyte', 'email' => 'rugile.plioplyte@example.com', 'phone' => '+37061235012'],
                    ['name' => 'Lina Bertaityte', 'email' => 'lina.bertaityte@example.com', 'phone' => '+37061235013'],
                    ['name' => 'Viltaras Kvedaras', 'email' => 'viltaras.kvedaras@example.com', 'phone' => '+37061235014'],
                    ['name' => 'Monika Vaiciulyte', 'email' => 'monika.vaiciulyte@example.com', 'phone' => '+37061235015'],
                    ['name' => 'Elze Mockute', 'email' => 'elze.mockute@example.com', 'phone' => '+37061235016'],
                    ['name' => 'Liepa Rimiene', 'email' => 'liepa.rimiene@example.com', 'phone' => '+37061235017'],
                    ['name' => 'Darius Venslovas', 'email' => 'darius.venslovas@example.com', 'phone' => '+37061235018'],
                    ['name' => 'Neringa Kuodyte', 'email' => 'neringa.kuodyte@example.com', 'phone' => '+37061235019'],
                    ['name' => 'Marius Giedraitis', 'email' => 'marius.giedraitis@example.com', 'phone' => '+37061235020'],
                ],
            ];

            $categories = $this->seedCategories()->keyBy('slug');
            $publishers = $this->seedPublishers()->keyBy('name');
            $authors = $this->seedAuthors()->keyBy('slug');
            $books = $this->seedBooks($categories, $publishers, $authors);

            [$branchesX, $locationsX] = $this->seedBranchesAndLocations($libraryX, [
                'Centras',
                'Senamiestis',
                'Zirmunai',
                'Antakalnis',
            ]);

            [$branchesY, $locationsY] = $this->seedBranchesAndLocations($libraryY, [
                'Centras',
                'Silainiai',
                'Dainava',
                'Kalniečiai',
            ]);

            $access = new DemoAccessActorSynchronizer();
            $actorsX = $access->syncLibrary($libraryX);
            $actorsY = $access->syncLibrary($libraryY);

            $membersX = $this->seedLibraryMembers($libraryX, $memberProfilesX)
                ->merge($actorsX['members'])
                ->unique('id')
                ->values();
            $membersY = $this->seedLibraryMembers($libraryY, $memberProfilesY)
                ->merge($actorsY['members'])
                ->unique('id')
                ->values();

            $employeesX = $actorsX['admins']->merge($actorsX['staff'])->values();
            $employeesY = $actorsY['admins']->merge($actorsY['staff'])->values();
            $staffX = $actorsX['staff']->first();

            $bookSample = $books->sortBy(fn (Book $book) => $book->isbn ?: $book->title)->values();
            $copiesX = $this->seedCopiesForLibrary($libraryX, $bookSample->take(min(20, $bookSample->count())), $branchesX, $locationsX, 'X', $employeesX, $membersX);
            $copiesY = $this->seedCopiesForLibrary($libraryY, $bookSample->skip(5)->take(min(20, $bookSample->count())), $branchesY, $locationsY, 'Y', $employeesY, $membersY);

            $this->seedReservationsForLibrary($libraryX, $copiesX, $membersX);
            $this->seedReservationsForLibrary($libraryY, $copiesY, $membersY);

            $this->seedScanLogs($libraryX, $copiesX, $employeesX);
            $this->seedScanLogs($libraryY, $copiesY, $employeesY);

            $this->seedAuditLogsForLibrary($libraryX, $books, $copiesX, $employeesX, $membersX);
            $this->seedAuditLogsForLibrary($libraryY, $books, $copiesY, $employeesY, $membersY);

            $eglePetrauskaite = $membersX->firstWhere('email', 'egle.petrauskaite@example.com');

            if ($eglePetrauskaite) {
                $this->seedNotificationCatalogForEglePetrauskaite($eglePetrauskaite, $staffX, $libraryX, $books);
            }
        });
    }

    /**
     * @param array{members: list<array{name: string, email: string, phone: string}>} $profiles
     * @return Collection<int, User>
     */
    private function seedLibraryMembers(Library $library, array $profiles): Collection
    {
        return collect($profiles['members'])
            ->values()
            ->map(function (array $member, int $index) use ($library) {
                $existingMembershipNumber = User::query()
                    ->where('email', $member['email'])
                    ->value('membership_number');

                $user = User::query()->updateOrCreate(
                    ['email' => $member['email']],
                    [
                        'name' => $member['name'],
                        'password' => Hash::make('password'),
                        'role' => 'narys',
                        'phone' => $member['phone'],
                        'membership_number' => str_starts_with((string) $existingMembershipNumber, 'MEM:')
                            ? $existingMembershipNumber
                            : UserManagement::generateMembershipNumber(),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                $this->attachLibraryMembership($user, $library);

                return $user;
            });
    }

    private function attachLibraryMembership(User $user, Library $library): void
    {
        LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $library->id,
                'user_id' => $user->id,
            ],
            [
                'membership_number' => $user->membership_number,
                'is_active' => $user->is_active,
                'joined_at' => $user->created_at,
            ]
        );
    }

    /**
     * @return Collection<int, Category>
     */
    private function seedCategories(): Collection
    {
        $names = [
            'Romanai',
            'Fantastika',
            'Detektyvai',
            'Istorija',
            'Biografijos',
            'Vaiku literatura',
            'Jaunimo literatura',
            'Psichologija',
            'Technologijos',
            'Menas',
            'Verslas',
            'Keliones',
            'Klasika',
            'Mokslas',
            'Vaikų ir jaunimo literatūra',
            'Vaikų literatūra',
            'Jaunimo literatūra',
            'Populiarioji psichologija',
            'protas, kūnas ir dvasia',
            'Meilės romanai',
            'Grožinė literatūra',
            'Kelionės',
            'Kultūra',
            'Publicistika',
            'Trileriai',
            'Savipagalba',
            'Šiuolaikinė literatūra',
            'Distopija',
            'Mokomoji literatūra',
            'Ekonomika',
            'Drama',
            'Istoriniai romanai',
            'Lietuvių literatūra',
        ];

        return collect($names)->map(function (string $name) {
            return Category::query()->updateOrCreate(
                ['name' => $name],
                [
                    'slug' => $this->uniqueCategorySlug($name),
                    'description' => $name.' skiltis bendram katalogui.',
                ]
            );
        })->values();
    }

    /**
     * @return Collection<int, Publisher>
     */
    private function seedPublishers(): Collection
    {
        $publishers = [
            ['name' => 'Alma littera', 'country' => 'Lietuva'],
            ['name' => 'Tyto alba', 'country' => 'Lietuva'],
            ['name' => 'Vaga', 'country' => 'Lietuva'],
            ['name' => 'Baltos lankos', 'country' => 'Lietuva'],
            ['name' => 'Eridanas', 'country' => 'Lietuva'],
            ['name' => 'Sofoklis', 'country' => 'Lietuva'],
            ['name' => 'Kitos knygos', 'country' => 'Lietuva'],
            ['name' => 'Nieko rimto', 'country' => 'Lietuva'],
            ['name' => 'Penguin Books', 'country' => 'United Kingdom'],
            ['name' => 'Random House', 'country' => 'United States'],
            ['name' => 'Obuolys', 'country' => 'Lietuva'],
            ['name' => 'Jotema', 'country' => 'Lietuva'],
            ['name' => 'Lietuvos rašytojų sąjungos leidykla', 'country' => 'Lietuva'],
            ['name' => 'Gamta', 'country' => 'Lietuva'],
        ];

        return collect($publishers)->map(function (array $publisher) {
            return Publisher::query()->firstOrCreate(
                ['name' => $publisher['name']],
                ['country' => $publisher['country']]
            );
        })->values();
    }

    /**
     * @return Collection<int, Author>
     */
    private function seedAuthors(): Collection
    {
        $authors = [
            ['name' => 'J. R. R. Tolkien', 'bio' => 'Britų rašytojas, sukūręs Viduržemės pasaulį.'],
            ['name' => 'George Orwell', 'bio' => 'Anglų rašytojas ir eseistas, zinomas del distopiniu romanu.'],
            ['name' => 'Antoine de Saint-Exupery', 'bio' => 'Prancūzų rašytojas ir lakūnas, parašęs Mažąjį princą.'],
            ['name' => 'James Clear', 'bio' => 'Autorius, tyrinėjantis įpročių formavimą ir kasdienius pokyčius.'],
            ['name' => 'Daniel Kahneman', 'bio' => 'Psichologas ir Nobelio premijos laureatas, tyrinėjęs sprendimų priėmimą.'],
            ['name' => 'Yuval Noah Harari', 'bio' => 'Izraelio istorikas, rašantis apie civilizacijos raidą.'],
            ['name' => 'Vincas Mykolaitis-Putinas', 'bio' => 'Lietuvių rašytojas, poetas ir literatūros istorikas.'],
            ['name' => 'Balys Sruoga', 'bio' => 'Lietuviu rasytojas ir dramaturgas.'],
            ['name' => 'Kristina Sabaliauskaitė', 'bio' => 'Lietuvių rašytoja, istorinio romano žanro atstovė.'],
            ['name' => 'Aldous Huxley', 'bio' => 'Anglų rašytojas, distopinio romano autorius.'],
            ['name' => 'Delia Owens', 'bio' => 'Amerikiečių rašytoja ir gamtininkė.'],
            ['name' => 'Frank Herbert', 'bio' => 'Amerikiečių fantastas, parašęs „Kopą“.'],
            ['name' => 'J. K. Rowling', 'bio' => 'Britų rašytoja, Hario Poterio serijos autorė.'],
            ['name' => 'John Green', 'bio' => 'Amerikiečių jaunimo literatūros autorius.'],
            ['name' => 'Andrzej Sapkowski', 'bio' => 'Lenkų fantastikos rašytojas, Raganiaus ciklo kūrėjas.'],
            ['name' => 'Sally Rooney', 'bio' => 'Airių rašytoja, rašanti apie šiuolaikinius santykius.'],
            ['name' => 'Richard Osman', 'bio' => 'Britų autorius, žinomas dėl lengvų detektyvų.'],
            ['name' => 'Tina Oziewicz', 'bio' => 'Lenkų autorė, kurianti vaikų literatūros knygas.'],
            ['name' => 'Marius Marcinkevičius', 'bio' => 'Lietuvių autorius, rašantis vaikams ir jaunimui.'],
            ['name' => 'J.K. Rowling', 'bio' => 'Britų rašytoja, Hario Poterio serijos autorė.'],
            ['name' => 'Benas Lyris', 'bio' => null],
            ['name' => 'Ilona Ežerinytė', 'bio' => null],
            ['name' => 'Robert Jobson', 'bio' => null],
            ['name' => 'Colleen Hoover', 'bio' => null],
            ['name' => 'Karel Čapek', 'bio' => null],
            ['name' => 'Andrius Kleiva', 'bio' => null],
            ['name' => 'Kate Atkinson', 'bio' => null],
            ['name' => 'Robin Norwood', 'bio' => null],
            ['name' => 'Kristin Hannah', 'bio' => null],
            ['name' => 'Emily St. John Mandel', 'bio' => null],
            ['name' => 'Dave Cousins', 'bio' => null],
            ['name' => 'Olivier Blanchard', 'bio' => null],
            ['name' => 'William Shakespeare', 'bio' => null],
            ['name' => 'Žiuljeta Benconi', 'bio' => null],
            ['name' => 'Bronius Radzevičius', 'bio' => null],
        ];

        return collect($authors)->map(function (array $author) {
            return Author::query()->updateOrCreate(
                ['name' => $author['name']],
                [
                    'slug' => $this->uniqueAuthorSlug($author['name']),
                    'bio' => $author['bio'],
                ]
            );
        })->values();
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @param  Collection<string, Publisher>  $publishers
     * @param  Collection<string, Author>  $authors
     * @return Collection<int, Book>
     */
    private function seedBooks(Collection $categories, Collection $publishers, Collection $authors): Collection
    {
        $catalog = [
            [
                'title' => 'Haris Poteris ir Išminties akmuo. 1 dalis',
                'isbn' => '9786090141601',
                'publisher' => 'Alma littera',
                'categories' => ['Vaikų ir jaunimo literatūra', 'Fantastika'],
                'authors' => ['J.K. Rowling'],
                'cover_image' => null,
            ],
            [
                'title' => 'Haris Poteris ir Išminties akmuo. Iliustruotas leidimas',
                'isbn' => '9786090150146',
                'publisher' => 'Alma littera',
                'categories' => ['Vaikų ir jaunimo literatūra', 'Fantastika'],
                'authors' => ['J.K. Rowling'],
                'cover_image' => null,
            ],
            [
                'title' => 'Haris Poteris ir Išminties akmuo. Pirmas leidimas',
                'isbn' => '9786090156995',
                'publisher' => 'Alma littera',
                'categories' => ['Vaikų ir jaunimo literatūra', 'Fantastika'],
                'authors' => ['J.K. Rowling'],
                'cover_image' => null,
            ],
            [
                'title' => 'Puslapis švelnumo',
                'isbn' => '9786090152683',
                'publisher' => 'Alma littera',
                'categories' => ['Populiarioji psichologija', 'protas, kūnas ir dvasia'],
                'authors' => ['Benas Lyris'],
                'cover_image' => 'https://almalittera.lt/cdn/shop/files/633fe4bf1a17a_3b792748-bffc-4724-8004-7e6e767f1f5c.jpg?v=1779811654&width=409',
            ],
            [
                'title' => 'Sutikti eidą',
                'isbn' => '9786090158005',
                'publisher' => 'Alma littera',
                'categories' => ['Jaunimo literatūra'],
                'authors' => ['Ilona Ežerinytė'],
                'cover_image' => null,
            ],
            [
                'title' => 'Velso princesė Catherine',
                'isbn' => '9786090166444',
                'publisher' => 'Alma littera',
                'categories' => ['Biografijos', 'Istorija'],
                'authors' => ['Robert Jobson'],
                'cover_image' => null,
            ],
            [
                'title' => 'Lapkričio 9',
                'isbn' => '9786094799716',
                'publisher' => 'Baltos lankos',
                'categories' => ['Romanai', 'Meilės romanai'],
                'authors' => ['Colleen Hoover'],
                'cover_image' => null,
            ],
            [
                'title' => 'Galbūt kažkada',
                'isbn' => '9786090900499',
                'publisher' => 'Baltos lankos',
                'categories' => ['Romanai', 'Meilės romanai'],
                'authors' => ['Colleen Hoover'],
                'cover_image' => null,
            ],
            [
                'title' => 'Jei ne tu',
                'isbn' => '9786094799327',
                'publisher' => 'Baltos lankos',
                'categories' => ['Romanai', 'Meilės romanai'],
                'authors' => ['Colleen Hoover'],
                'cover_image' => null,
            ],
            [
                'title' => 'Karas su salamandromis',
                'isbn' => '9786090404256',
                'publisher' => 'Obuolys',
                'categories' => ['Grožinė literatūra', 'Klasika', 'Fantastika'],
                'authors' => ['Karel Čapek'],
                'cover_image' => null,
            ],
            [
                'title' => 'Ką veikia Japonija',
                'isbn' => '9786094667251',
                'publisher' => 'Tyto alba',
                'categories' => ['Kelionės', 'Kultūra', 'Publicistika'],
                'authors' => ['Andrius Kleiva'],
                'cover_image' => null,
            ],
            [
                'title' => 'Užmirštos bylos',
                'isbn' => '9786094668913',
                'publisher' => 'Tyto alba',
                'categories' => ['Detektyvai', 'Trileriai'],
                'authors' => ['Kate Atkinson'],
                'cover_image' => null,
            ],
            [
                'title' => 'Moterys, kurios myli per stipriai',
                'isbn' => '9785415020980',
                'publisher' => 'Vaga',
                'categories' => ['Psichologija', 'Savipagalba'],
                'authors' => ['Robin Norwood'],
                'cover_image' => null,
            ],
            [
                'title' => 'Stebuklinga valanda',
                'isbn' => '9786094901751',
                'publisher' => 'Jotema',
                'categories' => ['Romanai', 'Šiuolaikinė literatūra'],
                'authors' => ['Kristin Hannah'],
                'cover_image' => null,
            ],
            [
                'title' => 'Vienuolikta stotis',
                'isbn' => '9789986399117',
                'publisher' => 'Lietuvos rašytojų sąjungos leidykla',
                'categories' => ['Grožinė literatūra', 'Distopija'],
                'authors' => ['Emily St. John Mandel'],
                'cover_image' => null,
            ],
            [
                'title' => 'Mano mokytojas yra robotas',
                'isbn' => '9786090141564',
                'publisher' => 'Alma littera',
                'categories' => ['Vaikų literatūra'],
                'authors' => ['Dave Cousins'],
                'cover_image' => null,
            ],
            [
                'title' => 'Makroekonomika',
                'isbn' => '9789986165453',
                'publisher' => 'Tyto alba',
                'categories' => ['Mokomoji literatūra', 'Ekonomika'],
                'authors' => ['Olivier Blanchard'],
                'cover_image' => null,
            ],
            [
                'title' => 'Hamletas',
                'isbn' => '9799955000357',
                'publisher' => 'Baltos lankos',
                'categories' => ['Drama', 'Klasika'],
                'authors' => ['William Shakespeare'],
                'cover_image' => null,
            ],
            [
                'title' => 'Katrina. I knyga',
                'isbn' => '9789986444220',
                'publisher' => 'Gamta',
                'categories' => ['Istoriniai romanai'],
                'authors' => ['Žiuljeta Benconi'],
                'cover_image' => null,
            ],
            [
                'title' => 'Priešaušrio vieškeliai',
                'isbn' => null,
                'publisher' => 'Lietuvos rašytojų sąjungos leidykla',
                'categories' => ['Lietuvių literatūra'],
                'authors' => ['Bronius Radzevičius'],
                'cover_image' => null,
            ],
        ];

        return collect($catalog)->map(function (array $book) use ($categories, $publishers, $authors) {
            $isbn = filled($book['isbn'] ?? null) ? $book['isbn'] : null;
            $categoryIds = collect($book['categories'])->map(fn (string $name) => $categories[GeneratesSlugs::from($name, 'kategorija')]->id);

            $record = Book::query()->updateOrCreate(
                $isbn ? ['isbn' => $isbn] : ['title' => $book['title']],
                [
                    'title' => $book['title'],
                    'subtitle' => null,
                    'isbn' => $isbn,
                    'description' => null,
                    'publisher_id' => $publishers[$book['publisher']]->id,
                    'category_id' => $categoryIds->first(),
                    'publication_year' => null,
                    'language' => 'lt',
                    'page_count' => null,
                    'edition' => null,
                    'cover_image' => $book['cover_image'],
                ]
            );

            $record->authors()->sync(
                collect($book['authors'])->map(fn (string $name) => $authors[GeneratesSlugs::from($name, 'autorius')]->id)->all()
            );

            $record->categories()->sync($categoryIds->all());

            return $record;
        })->values();
    }

    /**
     * @param  list<string>  $branchNames
     * @return array{0: Collection<int, Branch>, 1: Collection<int, Location>}
     */
    private function seedBranchesAndLocations(Library $library, array $branchNames): array
    {
        $branches = collect($branchNames)->map(function (string $branchName, int $index) use ($library) {
            return Branch::query()->updateOrCreate(
                [
                    'library_id' => $library->id,
                    'code' => $library->code.'-BR-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                ],
                [
                    'name' => $branchName,
                    'address' => $library->address,
                    'city' => $library->city,
                ]
            );
        });

        $locations = collect();

        foreach ($branches as $branch) {
            $locations = $locations->merge(
                collect([
                    ['name' => 'Suaugusiu grozine sale', 'room' => '1', 'shelf' => 'A-1'],
                    ['name' => 'Fantastikos lentyna', 'room' => '1', 'shelf' => 'B-2'],
                    ['name' => 'Humanitarikos skyrius', 'room' => '2', 'shelf' => 'C-1'],
                    ['name' => 'Vaiku ir jaunimo erdve', 'room' => '2', 'shelf' => 'D-4'],
                    ['name' => 'Krastotyros fondas', 'room' => '3', 'shelf' => 'E-2'],
                    ['name' => 'Sandelis', 'room' => '0', 'shelf' => 'ST-1'],
                ])->map(function (array $location, int $index) use ($library, $branch) {
                    return Location::query()->updateOrCreate(
                        [
                            'library_id' => $library->id,
                            'code' => $branch->code.'-LOC-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        ],
                        [
                            'branch_id' => $branch->id,
                            'name' => $location['name'],
                            'room' => $location['room'],
                            'shelf' => $location['shelf'],
                        ]
                    );
                })
            );
        }

        return [$branches->values(), $locations->values()];
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, User>  $employees
     * @param  Collection<int, User>  $members
     * @return Collection<int, BookCopy>
     */
    private function seedCopiesForLibrary(
        Library $library,
        Collection $books,
        Collection $branches,
        Collection $locations,
        string $prefix,
        Collection $employees,
        Collection $members
    ): Collection {
        $existingCopies = BookCopy::query()
            ->where('library_id', $library->id)
            ->where('inventory_code', 'like', sprintf('%s-%s-%%', $library->code, $prefix))
            ->get();

        if ($existingCopies->isNotEmpty()) {
            return $existingCopies->values();
        }

        $copies = collect();
        $inventoryCounter = 1;

        foreach ($books as $book) {
            $count = 3 + (($book->id + $inventoryCounter) % 4);

            for ($i = 0; $i < $count; $i++) {
                $sequence = $inventoryCounter + $i;
                $branch = $branches[($sequence - 1) % $branches->count()];
                $branchLocations = $locations->where('branch_id', $branch->id)->values();
                $location = $branchLocations[($sequence - 1) % max(1, $branchLocations->count())];
                $statuses = [
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_LOANED,
                    BookCopy::STATUS_LOANED,
                    BookCopy::STATUS_MAINTENANCE,
                    BookCopy::STATUS_LOST,
                    BookCopy::STATUS_WITHDRAWN,
                ];
                $targetStatus = $statuses[($sequence - 1) % count($statuses)];

                $condition = match ($targetStatus) {
                    BookCopy::STATUS_MAINTENANCE => [BookCopy::CONDITION_WORN, BookCopy::CONDITION_DAMAGED][$sequence % 2],
                    BookCopy::STATUS_LOST => [BookCopy::CONDITION_GOOD, BookCopy::CONDITION_WORN][$sequence % 2],
                    default => [BookCopy::CONDITION_NEW, BookCopy::CONDITION_GOOD, BookCopy::CONDITION_GOOD, BookCopy::CONDITION_WORN][$sequence % 4],
                };
                $currentInventory = $inventoryCounter++;

                $copy = BookCopy::create([
                    'library_id' => $library->id,
                    'book_id' => $book->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'inventory_code' => sprintf('%s-%s-%03d', $library->code, $prefix, $currentInventory),
                    'qr_code' => sprintf('QR-%s-%04d', $library->code, $currentInventory + 500),
                    'barcode' => '978'.str_pad((string) (1000000000 + $library->id * 10000 + $currentInventory), 10, '0', STR_PAD_LEFT),
                    'status' => BookCopy::STATUS_AVAILABLE,
                    'condition_status' => $condition,
                    'acquired_at' => Carbon::parse('2024-01-01')->subDays($currentInventory * 3)->format('Y-m-d'),
                    'notes' => $this->copyNotesForStatus($targetStatus),
                ]);

                $copies->push($copy);
                $this->recordCopyHistory($copy, $employees->first(), 'created', BookCopy::STATUS_AVAILABLE, 'Kopija įtrauktas į bibliotekos fondą.');

                if ($targetStatus === BookCopy::STATUS_LOANED) {
                    $this->seedLoanForCopy($copy, $members[($currentInventory - 1) % $members->count()], $employees[($currentInventory - 1) % $employees->count()], false, $currentInventory);

                    continue;
                }

                if ($targetStatus === BookCopy::STATUS_AVAILABLE) {
                    continue;
                }

                $copy->update(['status' => $targetStatus]);

                [$reasonCode, $notes] = match ($targetStatus) {
                    BookCopy::STATUS_MAINTENANCE => ['sent_to_maintenance', 'Kopija laikinai perduotas tvarkymui.'],
                    BookCopy::STATUS_LOST => ['marked_lost', 'Kopija nerasta po inventorizacijos.'],
                    BookCopy::STATUS_WITHDRAWN => ['nurašyta', 'Kopija nurašytas dėl nusidėvėjimo.'],
                    default => ['status_adjusted', 'Statusas atnaujintas demo duomenims.'],
                };

                $this->recordCopyHistory($copy, $employees[($currentInventory - 1) % $employees->count()], $reasonCode, $targetStatus, $notes);
            }
        }

        $availableCopies = $copies->filter(fn (BookCopy $copy) => $copy->status === BookCopy::STATUS_AVAILABLE)->values();

        foreach ($availableCopies->sortBy('inventory_code')->take(min(20, $availableCopies->count())) as $index => $copy) {
            $this->seedLoanForCopy($copy, $members[$index % $members->count()], $employees[$index % $employees->count()], true, $index + 100);
        }

        return $copies->values();
    }

    private function seedLoanForCopy(BookCopy $copy, User $member, User $employee, bool $returned, int $seedIndex): void
    {
        $borrowedAt = $this->safeTimestamp(now()->subDays(3 + ($seedIndex % 38))->subHours(1 + ($seedIndex % 8)));
        $dueAt = $this->safeTimestamp((clone $borrowedAt)->addDays(14));
        $returnedAt = $returned ? $this->safeTimestamp((clone $borrowedAt)->addDays(4 + ($seedIndex % 13))) : null;
        $status = $returned
            ? 'grąžinta'
            : (now()->gt($dueAt) ? 'vėluoja' : 'aktyvi');

        Loan::create([
            'library_id' => $copy->library_id,
            'book_copy_id' => $copy->id,
            'user_id' => $member->id,
            'issued_by' => $employee->id,
            'received_by' => $returned ? $employee->id : null,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'returned_at' => $returnedAt,
            'status' => $status,
            'renewal_count' => $returned ? $seedIndex % 2 : $seedIndex % 3,
            'notes' => $returned
                ? 'Demonstracinis grąžinimo įrašas.'
                : 'Skaitytojas šiuo metu naudojasi šia kopija.',
        ]);

        $copy->update(['status' => BookCopy::STATUS_LOANED]);
        $this->recordCopyHistory($copy, $employee, 'issued', BookCopy::STATUS_LOANED, 'Kopija išduota skaitytojui.');

        if ($returned) {
            $copy->update(['status' => BookCopy::STATUS_AVAILABLE]);
            $this->recordCopyHistory($copy, $employee, 'grąžinta', BookCopy::STATUS_AVAILABLE, 'Kopija grąžinta laiku ir vėl prieinamas fonde.');
        }
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     */
    private function seedReservationsForLibrary(Library $library, Collection $copies, Collection $members): void
    {
        if (Reservation::query()->where('library_id', $library->id)->exists()) {
            return;
        }

        $books = $copies->pluck('book')->filter()->unique('id')->values();

        foreach ($books->sortBy(fn (Book $book) => $book->isbn ?: $book->title)->take(min(16, $books->count())) as $bookIndex => $book) {
            $queuedMembers = $members->slice($bookIndex % $members->count(), 2 + ($bookIndex % 3))->values();
            $reservedAt = $this->safeTimestamp(now()->subDays(1 + ($bookIndex % 10))->subHours(1 + ($bookIndex % 10)));

            foreach ($queuedMembers as $position => $member) {
                $this->seedReservationForBook(
                    $library,
                    $book,
                    $member,
                    Reservation::STATUS_WAITING,
                    (clone $reservedAt)->addMinutes($position * 20),
                    null,
                    'Narys laukia, kol atsiras laisva šios knygos kopija.'
                );
            }

            foreach ($members->whereNotIn('id', $queuedMembers->pluck('id'))->values()->take(2 + (($bookIndex + 1) % 3)) as $historicalIndex => $historicalMember) {
                $historicalStatuses = [
                    Reservation::STATUS_FULFILLED,
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_EXPIRED,
                ];
                $historicalStatus = $historicalStatuses[($bookIndex + $historicalIndex) % count($historicalStatuses)];

                $historicalReservedAt = $this->safeTimestamp(now()->subDays(12 + (($bookIndex + $historicalIndex) % 14))->subHours(1 + (($bookIndex + $historicalIndex) % 12)));

                $this->seedReservationForBook(
                    $library,
                    $book,
                    $historicalMember,
                    $historicalStatus,
                    $historicalReservedAt,
                    $historicalStatus === Reservation::STATUS_EXPIRED ? now()->subDays(1 + (($bookIndex + $historicalIndex) % 4)) : null,
                    match ($historicalStatus) {
                        Reservation::STATUS_FULFILLED => 'Rezervacija buvo sėkmingai įvykdyta ir knyga atsiimta.',
                        Reservation::STATUS_CANCELLED => 'Narys atšaukė rezervaciją telefonu.',
                        Reservation::STATUS_EXPIRED => 'Narys laiku neatsiėmė knygos.',
                        default => null,
                    }
                );
            }
        }
    }

    private function seedReservationForBook(
        Library $library,
        Book $book,
        User $member,
        string $status,
        \DateTimeInterface $reservedAt,
        ?\DateTimeInterface $expiresAt,
        ?string $notes
    ): void {
        Reservation::create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'status' => $status,
            'pickup_branch_id' => $status === Reservation::STATUS_READY
                ? $book->bookCopies()
                    ->where('library_id', $library->id)
                    ->whereNotNull('branch_id')
                    ->orderBy('branch_id')
                    ->value('branch_id')
                : null,
            'reserved_at' => $reservedAt,
            'ready_at' => $status === Reservation::STATUS_READY ? $reservedAt : null,
            'expires_at' => in_array($status, [Reservation::STATUS_READY, Reservation::STATUS_EXPIRED], true)
                ? ($expiresAt ?? now()->addDays(4))
                : null,
            'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? Carbon::parse($reservedAt)->addDays(2) : null,
            'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? Carbon::parse($reservedAt)->addDay() : null,
            'notes' => $notes,
        ]);
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $employees
     */
    private function seedScanLogs(Library $library, Collection $copies, Collection $employees): void
    {
        if (ScanLog::query()->where('library_id', $library->id)->exists()) {
            return;
        }

        $scanTypes = ['info', 'loan', 'return', 'inventory'];
        $scanResults = ['success', 'success', 'success', 'error'];
        $devices = ['Samsung A54', 'Samsung Tab A9', 'Web scanner', 'Chrome Windows'];

        foreach ($copies->sortBy('inventory_code')->take(min(40, $copies->count())) as $index => $copy) {
            ScanLog::create([
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $employees[$index % $employees->count()]->id,
                'scan_value' => $copy->qr_code,
                'scan_type' => $scanTypes[$index % count($scanTypes)],
                'result' => $scanResults[$index % count($scanResults)],
                'device_info' => $devices[$index % count($devices)],
            ]);
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $employees
     * @param  Collection<int, User>  $members
     */
    private function seedAuditLogsForLibrary(
        Library $library,
        Collection $books,
        Collection $copies,
        Collection $employees,
        Collection $members
    ): void {
        if (AuditLog::query()
            ->where('library_id', $library->id)
            ->whereIn('action', ['book_updated', 'reservation_created', 'book_copy_status_changed', 'book_copy_updated', 'user_updated', 'loan_issued'])
            ->exists()) {
            return;
        }

        $bookSamples = $books->take(3)->values();

        foreach ($bookSamples as $bookIndex => $book) {
            foreach (range(1, 12) as $step) {
                $actor = $employees[($bookIndex + $step) % $employees->count()];
                $createdAt = $this->safeTimestamp(now()->subDays(40 - ($bookIndex * 8))->addHours($step));

                $this->createAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 3 === 0 ? 'book_updated' : 'reservation_created',
                    'auditable_type' => $book->getMorphClass(),
                    'auditable_id' => $book->id,
                    'description' => $step % 3 === 0
                        ? sprintf('Atnaujinta knygos "%s" informacija.', $book->title)
                        : sprintf('Sukurta rezervacija knygai "%s".', $book->title),
                    'metadata' => [
                        'book_id' => $book->id,
                        'book_title' => $book->title,
                        'target_member_id' => $members[($bookIndex + $step) % $members->count()]->id,
                    ],
                ], $createdAt);
            }
        }

        foreach ($copies->take(5)->values() as $copyIndex => $copy) {
            foreach (range(1, 10) as $step) {
                $actor = $employees[($copyIndex + $step) % $employees->count()];
                $createdAt = $this->safeTimestamp(now()->subDays(18 - $copyIndex)->addMinutes($step * 35));

                $this->createAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 2 === 0 ? 'book_copy_status_changed' : 'book_copy_updated',
                    'auditable_type' => $copy->getMorphClass(),
                    'auditable_id' => $copy->id,
                    'description' => $step % 2 === 0
                        ? sprintf('Kopijos %s statusas pakeistas.', $copy->inventory_code)
                        : sprintf('Atnaujinta kopijos %s informacija.', $copy->inventory_code),
                    'metadata' => [
                        'inventory_code' => $copy->inventory_code,
                        'target_status_label' => BookCopy::statusLabels()[$copy->status] ?? $copy->status,
                    ],
                ], $createdAt);
            }
        }

        foreach ($members->take(4)->values() as $memberIndex => $member) {
            foreach (range(1, 6) as $step) {
                $actor = $employees[($memberIndex + $step) % $employees->count()];
                $createdAt = $this->safeTimestamp(now()->subDays(12 - $memberIndex)->addMinutes($step * 50));

                $this->createAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 2 === 0 ? 'user_updated' : 'loan_issued',
                    'auditable_type' => $member->getMorphClass(),
                    'auditable_id' => $member->id,
                    'description' => $step % 2 === 0
                        ? sprintf('Atnaujintas vartotojas "%s".', $member->name)
                        : sprintf('Knyga išduota vartotojui "%s".', $member->name),
                    'metadata' => [
                        'target_member_id' => $member->id,
                        'target_member_name' => $member->name,
                    ],
                ], $createdAt);
            }
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     */
    private function seedNotificationCatalogForEglePetrauskaite(User $member, User $sender, Library $library, Collection $books): void
    {
        $member->notifications()
            ->get()
            ->filter(fn ($notification) => (bool) data_get($notification->data, 'metadata.demo_notification_catalog'))
            ->each->delete();

        $bookTitle = $books->first()?->title ?: 'Demo knyga';
        $notificationDefinitions = [
            NotificationType::RESERVATION_CREATED->value => [
                'title' => 'Rezervacija sukurta',
                'message' => sprintf('Jūs sėkmingai rezervavote knygą "%s". Jūsų vieta eilėje: 1.', $bookTitle),
            ],
            NotificationType::RESERVATION_QUEUE_CHANGED->value => [
                'title' => 'Rezervacijos eilė pasikeitė',
                'message' => sprintf('Knygos "%s" rezervacijos eilėje dabar esate 1 vietoje.', $bookTitle),
            ],
            NotificationType::RESERVATION_READY->value => [
                'title' => 'Rezervacija paruošta',
                'message' => sprintf('Knyga "%s" jau laukia jūsų. Atsiimkite iki rytojaus darbo pabaigos.', $bookTitle),
            ],
            NotificationType::RESERVATION_CANCELLED->value => [
                'title' => 'Rezervacija atšaukta',
                'message' => sprintf('Jūsų rezervacija knygai "%s" buvo atšaukta bibliotekos darbuotojo.', $bookTitle),
            ],
            NotificationType::RESERVATION_EXPIRED->value => [
                'title' => 'Rezervacijos galiojimas baigėsi',
                'message' => sprintf('Rezervacijos knygai "%s" atsiėmimo terminas baigėsi.', $bookTitle),
            ],
            NotificationType::RESERVATION_FULFILLED->value => [
                'title' => 'Rezervacija įvykdyta',
                'message' => sprintf('Pagal jūsų rezervaciją išduota knyga "%s".', $bookTitle),
            ],
            NotificationType::LOAN_OVERDUE->value => [
                'title' => 'Vėluojate grąžinti knygą',
                'message' => sprintf('Knygos "%s" grąžinimo terminas jau praėjo. Prašome susisiekti su biblioteka.', $bookTitle),
            ],
            NotificationType::BOOK_DUE_SOON->value => [
                'title' => 'Artėja grąžinimo terminas',
                'message' => sprintf('Knygą "%s" reikės grąžinti per artimiausias 2 dienas.', $bookTitle),
            ],
            NotificationType::BOOK_RETURNED->value => [
                'title' => 'Knyga grąžinta',
                'message' => sprintf('Knyga "%s" sėkmingai grąžinta. Ačiū, kad naudojatės biblioteka.', $bookTitle),
            ],
            NotificationType::LIBRARY_MEMBERSHIP_ADDED->value => [
                'title' => 'Pridėta bibliotekos narystė',
                'message' => sprintf('Jūs buvote pridėta prie bibliotekos "%s".', $library->name),
            ],
            NotificationType::SYSTEM->value => [
                'title' => 'Sistemos pranešimas',
                'message' => 'Bibliotekos sistema atnaujino jūsų paskyros informaciją.',
            ],
            NotificationType::NEW_USER->value => [
                'title' => 'Paskyra aktyvuota',
                'message' => 'Jūsų skaitytojo paskyra aktyvuota ir paruošta naudojimui.',
            ],
            NotificationType::QR_SCAN->value => [
                'title' => 'QR kodas nuskaitytas',
                'message' => 'Jūsų skaitytojo QR kodas sėkmingai nuskaitytas bibliotekoje.',
            ],
            NotificationType::REPORT_READY->value => [
                'title' => 'Ataskaita paruošta',
                'message' => 'Jūsų prašyta bibliotekos ataskaita paruošta peržiūrai.',
            ],
            NotificationType::ISSUANCE_SUMMARY->value => [
                'title' => 'Išdavimo suvestinė',
                'message' => 'Paruošta nauja jūsų išduotų ir grąžintų knygų suvestinė.',
            ],
            NotificationType::SYSTEM_WARNING->value => [
                'title' => 'Sistemos įspėjimas',
                'message' => 'Sistemai reikalingas jūsų dėmesys: patikrinkite paskyros duomenis.',
            ],
            NotificationType::SYSTEM_ERROR->value => [
                'title' => 'Sistemos klaida',
                'message' => 'Nepavyko atlikti vieno veiksmo. Bandykite dar kartą arba kreipkitės į biblioteką.',
            ],
            NotificationType::ACCOUNT_SECURITY->value => [
                'title' => 'Paskyros saugumas',
                'message' => 'Užfiksuotas naujas prisijungimas prie jūsų paskyros.',
            ],
        ];

        foreach ($notificationDefinitions as $index => $definition) {
            $kind = (string) $index;
            $ui = NotificationUiConfig::for($kind);
            $id = (string) Str::uuid();
            $createdAt = now()->subMinutes((count($notificationDefinitions) - array_search($kind, array_keys($notificationDefinitions), true)) * 8);

            $member->notifications()->create([
                'id' => $id,
                'type' => $kind,
                'data' => [
                    'kind' => $kind,
                    'type' => $ui['type'],
                    'ui' => $ui,
                    'category' => $ui['category'],
                    'icon' => $ui['icon'],
                    'color' => $ui['color'],
                    'notification_id' => $id,
                    'title' => $definition['title'],
                    'body' => $definition['message'],
                    'message' => $definition['message'],
                    'url' => route('notifications.index', absolute: false),
                    'deep_link' => "libraryapp://notification/{$id}",
                    'created_at' => $createdAt->toIso8601String(),
                    'related_type' => null,
                    'related_id' => null,
                    'metadata' => [
                        'demo_notification_catalog' => true,
                        'library_id' => $library->id,
                        'library_name' => $library->name,
                        'book_title' => $bookTitle,
                        'sender' => [
                            'id' => $sender->id,
                            'name' => $sender->name,
                            'email' => $sender->email,
                        ],
                    ],
                ],
                'read_at' => $kind === NotificationType::SYSTEM->value ? $createdAt->copy()->addMinutes(5) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function createAuditLog(array $attributes, CarbonInterface $createdAt): void
    {
        $log = AuditLog::create($attributes);
        $log->timestamps = false;
        $log->created_at = $createdAt;
        $log->updated_at = $createdAt;
        $log->save();
    }

    private function copyNotesForStatus(string $status): ?string
    {
        return match ($status) {
            BookCopy::STATUS_AVAILABLE => [
                null,
                'Kopija tvarkinga ir prieinama skaitytojams.',
                'Pastaruoju metu dažnai ieškoma prie informacijos stalo.',
            ][strlen($status) % 3],
            BookCopy::STATUS_LOANED => 'Kopija šiuo metu išduota skaitytojui.',
            BookCopy::STATUS_MAINTENANCE => 'Laukiama smulkaus taisymo arba perklijavimo.',
            BookCopy::STATUS_LOST => 'Nepavyko rasti per paskutinę inventorizaciją.',
            BookCopy::STATUS_WITHDRAWN => 'Kopija nebepriklauso aktyviam bibliotekos fondui.',
            default => null,
        };
    }

    private function recordCopyHistory(BookCopy $copy, ?User $user, string $reasonCode, string $toStatus, ?string $notes = null): void
    {
        $fromStatus = BookCopyStatusHistory::query()
            ->where('book_copy_id', $copy->id)
            ->latest('changed_at')
            ->value('to_status');

        $lastChangedAt = BookCopyStatusHistory::query()
            ->where('book_copy_id', $copy->id)
            ->max('changed_at');

        $changedAt = $lastChangedAt
            ? Carbon::parse($lastChangedAt)->addHours(12)
            : ($copy->acquired_at
                ? Carbon::parse($copy->acquired_at)->startOfDay()->addDays(2)->addHours(10)
                : now()->subMonths(6)->addDays(2)->addHours(10));

        $changedAt = $this->safeTimestamp($changedAt);

        BookCopyStatusHistory::create([
            'book_copy_id' => $copy->id,
            'changed_by' => $user?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
            'reason_notes' => $notes,
            'changed_at' => $changedAt,
        ]);
    }

    private function safeTimestamp(CarbonInterface $timestamp): CarbonInterface
    {
        $safe = $timestamp instanceof Carbon
            ? $timestamp->copy()
            : Carbon::instance($timestamp);

        if ((int) $safe->format('H') === 3) {
            return $safe->setTime(4, 0);
        }

        return $safe;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = GeneratesSlugs::from($name, 'kategorija');
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->where('name', '!=', $name)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function uniqueAuthorSlug(string $name): string
    {
        $base = GeneratesSlugs::from($name, 'autorius');
        $slug = $base;
        $suffix = 2;

        while (Author::query()->where('slug', $slug)->where('name', '!=', $name)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
