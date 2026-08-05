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
use App\Models\UserNotification;
use App\Support\GeneratesSlugs;
use App\Support\Notifications\NotificationType;
use App\Support\Notifications\NotificationUiConfig;
use App\Support\UserManagement;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\Support\DemoAccessActorSynchronizer;
use Database\Seeders\Support\DemoDatasetMarker;
use Database\Seeders\Support\GuardsDemoSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
class DemoDataSeeder extends Seeder
{
    use GuardsDemoSeeding;

    /**
     * Seed the complete demo dataset in the only supported order.
     */
    public function run(): void
    {
        $this->guardDemoSeedingIsAllowed();

        $this->coreSeedLibrariesBranchesCatalogAndBaseScenarios();
        $this->kaltSeedKaltinenaiLibraryScenarios();
        $this->presentationSeedPresentationDataset();
        $this->normalizeDemoBookAndCopyLabels();
        $this->validateSeededData();
    }

    private function normalizeDemoBookAndCopyLabels(): void
    {
        $this->normalizeDemoBookTitles();
        $this->normalizeDemoCopyCodes();
    }

    private function normalizeDemoBookTitles(): void
    {
        Book::query()
            ->whereHas('bookCopies.library')
            ->chunkById(100, function (Collection $books): void {
                foreach ($books as $book) {
                    $title = preg_replace('/^\[[^\]]+\]\s*/', '', (string) $book->title);

                    if ($book->title !== $title) {
                        $book->forceFill(['title' => $title])->save();
                    }
                }
            });
    }

    private function normalizeDemoCopyCodes(): void
    {
        Library::query()
            ->whereIn('code', ['LIB-X', 'LIB-Y', 'KALT-ASTU-001'])
            ->with('branches:id,library_id,code')
            ->get()
            ->each(function (Library $library): void {
                foreach ($library->branches as $branch) {
                    BookCopy::query()
                        ->where('library_id', $library->id)
                        ->where('branch_id', $branch->id)
                        ->orderBy('id')
                        ->get()
                        ->values()
                        ->each(function (BookCopy $copy, int $index) use ($library, $branch): void {
                            $inventoryCode = sprintf('%s-%03d', $this->demoCopyCodePrefix($library, $branch), $index + 1);
                            $qrCode = 'QR-'.$inventoryCode;

                            if ($copy->inventory_code !== $inventoryCode || $copy->qr_code !== $qrCode) {
                                $copy->forceFill([
                                    'inventory_code' => $inventoryCode,
                                    'qr_code' => $qrCode,
                                ])->save();
                            }
                        });
                }
            });
    }

    private function demoCopyCodePrefix(Library $library, Branch $branch): string
    {
        return str_starts_with($branch->code, $library->code.'-')
            ? $branch->code
            : $library->code.'-'.$branch->code;
    }

    private function validateSeededData(): void
    {
        $branchlessStaff = LibraryMembership::query()
            ->join('users', 'users.id', '=', 'library_memberships.user_id')
            ->where('users.role', User::ROLE_STAFF)
            ->where('users.is_active', true)
            ->where('library_memberships.is_active', true)
            ->whereNull('library_memberships.branch_id')
            ->count();

        if ($branchlessStaff > 0) {
            throw new InvalidArgumentException('Demo seed left active staff memberships without branch_id: '.$branchlessStaff);
        }

        $foreignBranchStaff = LibraryMembership::query()
            ->join('users', 'users.id', '=', 'library_memberships.user_id')
            ->join('branches', 'branches.id', '=', 'library_memberships.branch_id')
            ->where('users.role', User::ROLE_STAFF)
            ->where('users.is_active', true)
            ->where('library_memberships.is_active', true)
            ->whereColumn('branches.library_id', '<>', 'library_memberships.library_id')
            ->count();

        if ($foreignBranchStaff > 0) {
            throw new InvalidArgumentException('Demo seed left staff assigned to branches from another library: '.$foreignBranchStaff);
        }
    }
    private function coreSeedLibrariesBranchesCatalogAndBaseScenarios(): void
    {
        $this->guardDemoSeedingIsAllowed();

        DB::transaction(function () {
            $libraryX = Library::query()->updateOrCreate(
                ['code' => 'LIB-X'],
                [
                    'name' => 'Vilniaus miesto centrinė biblioteka',
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
                    'name' => 'Kauno rajono viešoji biblioteka',
                    'email' => 'kaunas@library.test',
                    'phone' => '+37060000002',
                    'address' => 'Laisvės al. 48',
                    'city' => 'Kaunas',
                    'is_active' => true,
                    'is_public' => true,
                ]
            );

            (new DemoAccessActorSynchronizer())->syncSuperadmins();

            $memberProfilesX = [
                'members' => [
                    ['name' => 'Austėja Kazlauskaitė', 'email' => 'austeja.kazlauskaite@example.com', 'phone' => '+37061234001'],
                    ['name' => 'Mantas Balsevičius', 'email' => 'mantas.balsevicius@example.com', 'phone' => '+37061234002'],
                    ['name' => 'Eglė Petrauskaitė', 'email' => 'egle.petrauskaite@example.com', 'phone' => '+37061234003'],
                    ['name' => 'Lukas Vaitiekūnas', 'email' => 'lukas.vaitiekunas@example.com', 'phone' => '+37061234004'],
                    ['name' => 'Saulė Grigaitytė', 'email' => 'saule.grigaityte@example.com', 'phone' => '+37061234005'],
                    ['name' => 'Rokas Jankauskas', 'email' => 'rokas.jankauskas@example.com', 'phone' => '+37061234006'],
                    ['name' => 'Gabija Rimkutė', 'email' => 'gabija.rimkute@example.com', 'phone' => '+37061234007'],
                    ['name' => 'Emilija Varnytė', 'email' => 'emilija.varnyte@example.com', 'phone' => '+37061234008'],
                    ['name' => 'Nojus Pocius', 'email' => 'nojus.pocius@example.com', 'phone' => '+37061234009'],
                    ['name' => 'Milda Janušauskaitė', 'email' => 'milda.janusauskaite@example.com', 'phone' => '+37061234010'],
                    ['name' => 'Tadas Veverskis', 'email' => 'tadas.veverskis@example.com', 'phone' => '+37061234011'],
                    ['name' => 'Karolina Butkevičiūtė', 'email' => 'karolina.butkeviciute@example.com', 'phone' => '+37061234012'],
                    ['name' => 'Simona Petraštytė', 'email' => 'simona.petratyte@example.com', 'phone' => '+37061234013'],
                    ['name' => 'Giedrė Valentienė', 'email' => 'giedre.valentiene@example.com', 'phone' => '+37061234014'],
                    ['name' => 'Tomas Vaičkus', 'email' => 'tomas.vaiktus@example.com', 'phone' => '+37061234015'],
                    ['name' => 'Aistė Jakaitė', 'email' => 'aiste.jakaite@example.com', 'phone' => '+37061234016'],
                    ['name' => 'Urtė Žukaitė', 'email' => 'urte.zukaite@example.com', 'phone' => '+37061234017'],
                    ['name' => 'Dovilė Kairienė', 'email' => 'dovile.kairiene@example.com', 'phone' => '+37061234018'],
                    ['name' => 'Milda Gerdvilaitė', 'email' => 'milda.gerdvilaite@example.com', 'phone' => '+37061234019'],
                    ['name' => 'Povilas Morkūnas', 'email' => 'povilas.morkunas@example.com', 'phone' => '+37061234020'],
                ],
            ];

            $memberProfilesY = [
                'members' => [
                    ['name' => 'Ieva Noreikaitė', 'email' => 'ieva.noreikaite@example.com', 'phone' => '+37061235001'],
                    ['name' => 'Domas Vasiliauskas', 'email' => 'domas.vasiliauskas@example.com', 'phone' => '+37061235002'],
                    ['name' => 'Goda Lukočevičiūtė', 'email' => 'goda.lukoceviciute@example.com', 'phone' => '+37061235003'],
                    ['name' => 'Ugnius Narbutas', 'email' => 'ugnius.narbutas@example.com', 'phone' => '+37061235004'],
                    ['name' => 'Vakarė Simonaitytė', 'email' => 'vakare.simonaityte@example.com', 'phone' => '+37061235005'],
                    ['name' => 'Jonas Petraitis', 'email' => 'jonas.petraitis@example.com', 'phone' => '+37061235006'],
                    ['name' => 'Aistė Mačiulytė', 'email' => 'aiste.maciulyte@example.com', 'phone' => '+37061235007'],
                    ['name' => 'Pijus Zabiela', 'email' => 'pijus.zabiela@example.com', 'phone' => '+37061235008'],
                    ['name' => 'Greta Šimkutė', 'email' => 'greta.simkute@example.com', 'phone' => '+37061235009'],
                    ['name' => 'Nedas Petrauskas', 'email' => 'nedas.petrauskas@example.com', 'phone' => '+37061235010'],
                    ['name' => 'Paulina Stankutė', 'email' => 'paulina.stankute@example.com', 'phone' => '+37061235011'],
                    ['name' => 'Rugilė Plioplytė', 'email' => 'rugile.plioplyte@example.com', 'phone' => '+37061235012'],
                    ['name' => 'Lina Bertaičiūtė', 'email' => 'lina.bertaityte@example.com', 'phone' => '+37061235013'],
                    ['name' => 'Viltaras Kvedaras', 'email' => 'viltaras.kvedaras@example.com', 'phone' => '+37061235014'],
                    ['name' => 'Monika Vaičiulytė', 'email' => 'monika.vaiciulyte@example.com', 'phone' => '+37061235015'],
                    ['name' => 'Elzė Mockutė', 'email' => 'elze.mockute@example.com', 'phone' => '+37061235016'],
                    ['name' => 'Liepa Rimienė', 'email' => 'liepa.rimiene@example.com', 'phone' => '+37061235017'],
                    ['name' => 'Darius Venslovas', 'email' => 'darius.venslovas@example.com', 'phone' => '+37061235018'],
                    ['name' => 'Neringa Kuodytė', 'email' => 'neringa.kuodyte@example.com', 'phone' => '+37061235019'],
                    ['name' => 'Marius Giedraitis', 'email' => 'marius.giedraitis@example.com', 'phone' => '+37061235020'],
                ],
            ];

            $categories = $this->coreSeedCategories()->keyBy('slug');
            $publishers = $this->coreSeedPublishers()->keyBy('name');
            $authors = $this->coreSeedAuthors()->keyBy('slug');
            $books = $this->coreSeedBooks($categories, $publishers, $authors);

            [$branchesX, $locationsX] = $this->coreSeedBranchesAndLocations($libraryX, [
                'Centras',
                'Senamiestis',
                'Žirmūnai',
                'Antakalnis',
            ]);

            [$branchesY, $locationsY] = $this->coreSeedBranchesAndLocations($libraryY, [
                'Centras',
                'Šilainiai',
                'Dainava',
                'Kalniečiai',
            ]);

            $access = new DemoAccessActorSynchronizer();
            $actorsX = $access->syncLibrary($libraryX);
            $actorsY = $access->syncLibrary($libraryY);

            $membersX = $this->coreSeedLibraryMembers($libraryX, $memberProfilesX)
                ->merge($actorsX['members'])
                ->unique('id')
                ->values();
            $membersY = $this->coreSeedLibraryMembers($libraryY, $memberProfilesY)
                ->merge($actorsY['members'])
                ->unique('id')
                ->values();

            $employeesX = $actorsX['admins']->merge($actorsX['staff'])->values();
            $employeesY = $actorsY['admins']->merge($actorsY['staff'])->values();
            $staffX = $actorsX['staff']->first();

            $bookSample = $books->sortBy(fn (Book $book) => $book->isbn ?: $book->title)->values();
            $copiesX = $this->coreSeedCopiesForLibrary($libraryX, $bookSample->take(min(20, $bookSample->count())), $branchesX, $locationsX, 'X', $employeesX, $membersX);
            $copiesY = $this->coreSeedCopiesForLibrary($libraryY, $bookSample->skip(5)->take(min(20, $bookSample->count())), $branchesY, $locationsY, 'Y', $employeesY, $membersY);

            $this->coreSeedReservationsForLibrary($libraryX, $copiesX, $membersX);
            $this->coreSeedReservationsForLibrary($libraryY, $copiesY, $membersY);

            $this->coreSeedScanLogs($libraryX, $copiesX, $employeesX);
            $this->coreSeedScanLogs($libraryY, $copiesY, $employeesY);

            $this->coreSeedAuditLogsForLibrary($libraryX, $books, $copiesX, $employeesX, $membersX);
            $this->coreSeedAuditLogsForLibrary($libraryY, $books, $copiesY, $employeesY, $membersY);

            $eglePetrauskaite = $membersX->firstWhere('email', 'egle.petrauskaite@example.com');

            if ($eglePetrauskaite) {
                $this->coreSeedNotificationCatalogForEglePetrauskaite($eglePetrauskaite, $staffX, $libraryX, $books);
            }
        });
    }

    /**
     * @param array{members: list<array{name: string, email: string, phone: string}>} $profiles
     * @return Collection<int, User>
     */
    private function coreSeedLibraryMembers(Library $library, array $profiles): Collection
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

                $this->coreAttachLibraryMembership($user, $library);

                return $user;
            });
    }

    private function coreAttachLibraryMembership(User $user, Library $library): void
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
    private function coreSeedCategories(): Collection
    {
        $names = [
            'Romanai',
            'Fantastika',
            'Detektyvai',
            'Istorija',
            'Biografijos',
            'Vaikų literatūra',
            'Jaunimo literatura',
            'Psichologija',
            'Technologijos',
            'Menas',
            'Verslas',
            'Kelionės',
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
                    'slug' => $this->coreUniqueCategorySlug($name),
                    'description' => $name.' skiltis bendram katalogui.',
                ]
            );
        })->values();
    }

    /**
     * @return Collection<int, Publisher>
     */
    private function coreSeedPublishers(): Collection
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
    private function coreSeedAuthors(): Collection
    {
        $authors = [
            ['name' => 'J. R. R. Tolkien', 'bio' => 'Britų rašytojas, sukūręs Viduržemės pasaulį.'],
            ['name' => 'George Orwell', 'bio' => 'Anglų rašytojas ir eseistas, žinomas dėl distopinių romanų.'],
            ['name' => 'Antoine de Saint-Exupéry', 'bio' => 'Prancūzų rašytojas ir lakūnas, parašęs Mažąjį princą.'],
            ['name' => 'James Clear', 'bio' => 'Autorius, tyrinėjantis įpročių formavimą ir kasdienius pokyčius.'],
            ['name' => 'Daniel Kahneman', 'bio' => 'Psichologas ir Nobelio premijos laureatas, tyrinėjęs sprendimų priėmimą.'],
            ['name' => 'Yuval Noah Harari', 'bio' => 'Izraelio istorikas, rašantis apie civilizacijos raidą.'],
            ['name' => 'Vincas Mykolaitis-Putinas', 'bio' => 'Lietuvių rašytojas, poetas ir literatūros istorikas.'],
            ['name' => 'Balys Sruoga', 'bio' => 'Lietuvių rašytojas ir dramaturgas.'],
            ['name' => 'Kristina Sabaliauskaitė', 'bio' => 'Lietuvių rašytoja, istorinio romano žanro atstovė.'],
            ['name' => 'Aldous Huxley', 'bio' => 'Anglų rašytojas, distopinio romano autorius.'],
            ['name' => 'Delia Owens', 'bio' => 'Amerikiečių rašytoja ir gamtininkė.'],
            ['name' => 'Frank Herbert', 'bio' => 'Amerikiečių fantastas, parašęs Kopą.'],
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
            ['name' => 'Džiuljeta Benconi', 'bio' => null],
            ['name' => 'Bronius Radzevičius', 'bio' => null],
        ];

        return collect($authors)->map(function (array $author) {
            return Author::query()->updateOrCreate(
                ['name' => $author['name']],
                [
                    'slug' => $this->coreUniqueAuthorSlug($author['name']),
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
    private function coreSeedBooks(Collection $categories, Collection $publishers, Collection $authors): Collection
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
                'authors' => ['Džiuljeta Benconi'],
                'cover_image' => null,
            ],
            [
                'title' => 'Priešaušrio vieškeliai',
                'isbn' => '9786090000001',
                'publisher' => 'Lietuvos rašytojų sąjungos leidykla',
                'categories' => ['Lietuvių literatūra'],
                'authors' => ['Bronius Radzevičius'],
                'cover_image' => null,
            ],
        ];

        return collect($catalog)->map(function (array $book) use ($categories, $publishers, $authors) {
            $isbn = filled($book['isbn'] ?? null) ? $book['isbn'] : null;
            $categoryIds = collect($book['categories'])->map(fn (string $name) => $categories[GeneratesSlugs::from($name, 'kategorija')]->id);

            $record = $isbn
                ? Book::query()->firstOrNew(['isbn' => $isbn])
                : Book::query()
                    ->where('title', $book['title'])
                    ->orWhere('title', 'like', '%] '.$book['title'])
                    ->firstOrNew(['title' => $book['title']]);

            $record->fill([
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
            ])->save();

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
    private function coreSeedBranchesAndLocations(Library $library, array $branchNames): array
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
                    ['name' => 'Vaikų ir jaunimo erdvė', 'room' => '2', 'shelf' => 'D-4'],
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
    private function coreSeedCopiesForLibrary(
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
            ->where('inventory_code', 'like', $library->code.'-%')
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
                    'inventory_code' => sprintf('%s-%03d', $this->demoCopyCodePrefix($library, $branch), $currentInventory),
                    'qr_code' => sprintf('QR-%s-%03d', $this->demoCopyCodePrefix($library, $branch), $currentInventory),
                    'barcode' => '978'.str_pad((string) (1000000000 + $library->id * 10000 + $currentInventory), 10, '0', STR_PAD_LEFT),
                    'status' => BookCopy::STATUS_AVAILABLE,
                    'condition_status' => $condition,
                    'acquired_at' => Carbon::parse('2024-01-01')->subDays($currentInventory * 3)->format('Y-m-d'),
                    'notes' => $this->coreCopyNotesForStatus($targetStatus),
                ]);

                $copies->push($copy);
                $this->coreRecordCopyHistory($copy, $employees->first(), 'created', BookCopy::STATUS_AVAILABLE, 'Kopija trauktas  bibliotekos fond.');

                if ($targetStatus === BookCopy::STATUS_LOANED) {
                    $this->coreSeedLoanForCopy($copy, $members[($currentInventory - 1) % $members->count()], $employees[($currentInventory - 1) % $employees->count()], false, $currentInventory);

                    continue;
                }

                if ($targetStatus === BookCopy::STATUS_AVAILABLE) {
                    continue;
                }

                $copy->update(['status' => $targetStatus]);

                [$reasonCode, $notes] = match ($targetStatus) {
                    BookCopy::STATUS_MAINTENANCE => ['sent_to_maintenance', 'Kopija laikinai perduotas tvarkymui.'],
                    BookCopy::STATUS_LOST => ['marked_lost', 'Kopija nerasta po inventorizacijos.'],
                    BookCopy::STATUS_WITHDRAWN => ['nurašyta', 'Kopija nurašyta dėl nusidėvėjimo.'],
                    default => ['status_adjusted', 'Statusas atnaujintas demo duomenims.'],
                };

                $this->coreRecordCopyHistory($copy, $employees[($currentInventory - 1) % $employees->count()], $reasonCode, $targetStatus, $notes);
            }
        }

        $availableCopies = $copies->filter(fn (BookCopy $copy) => $copy->status === BookCopy::STATUS_AVAILABLE)->values();

        foreach ($availableCopies->sortBy('inventory_code')->take(min(20, $availableCopies->count())) as $index => $copy) {
            $this->coreSeedLoanForCopy($copy, $members[$index % $members->count()], $employees[$index % $employees->count()], true, $index + 100);
        }

        return $copies->values();
    }

    private function coreSeedLoanForCopy(BookCopy $copy, User $member, User $employee, bool $returned, int $seedIndex): void
    {
        $borrowedAt = $this->coreSafeTimestamp(now()->subDays(3 + ($seedIndex % 38))->subHours(1 + ($seedIndex % 8)));
        $dueAt = $this->coreSafeTimestamp((clone $borrowedAt)->addDays(14));
        $returnedAt = $returned ? $this->coreSafeTimestamp((clone $borrowedAt)->addDays(4 + ($seedIndex % 13))) : null;
        $status = $returned
            ? Loan::STATUS_RETURNED
            : (now()->gt($dueAt) ? Loan::STATUS_OVERDUE : Loan::STATUS_ACTIVE);

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
                : 'Skaitytojas iuo metu naudojasi ia kopija.',
        ]);

        $copy->update(['status' => BookCopy::STATUS_LOANED]);
        $this->coreRecordCopyHistory($copy, $employee, 'issued', BookCopy::STATUS_LOANED, 'Kopija išduota skaitytojui.');

        if ($returned) {
            $copy->update(['status' => BookCopy::STATUS_AVAILABLE]);
            $this->coreRecordCopyHistory($copy, $employee, 'grąžinta', BookCopy::STATUS_AVAILABLE, 'Kopija grąžinta laiku ir vėl prieinama fonde.');
        }
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     */
    private function coreSeedReservationsForLibrary(Library $library, Collection $copies, Collection $members): void
    {
        if (Reservation::query()->where('library_id', $library->id)->exists()) {
            return;
        }

        $books = $copies->pluck('book')->filter()->unique('id')->values();

        foreach ($books->sortBy(fn (Book $book) => $book->isbn ?: $book->title)->take(min(16, $books->count())) as $bookIndex => $book) {
            $queuedMembers = $members->slice($bookIndex % $members->count(), 2 + ($bookIndex % 3))->values();
            $reservedAt = $this->coreSafeTimestamp(now()->subDays(1 + ($bookIndex % 10))->subHours(1 + ($bookIndex % 10)));

            foreach ($queuedMembers as $position => $member) {
                $this->coreSeedReservationForBook(
                    $library,
                    $book,
                    $member,
                    Reservation::STATUS_WAITING,
                    (clone $reservedAt)->addMinutes($position * 20),
                    null,
                    'Narys laukia, kol atsiras laisva ios knygos kopija.'
                );
            }

            foreach ($members->whereNotIn('id', $queuedMembers->pluck('id'))->values()->take(2 + (($bookIndex + 1) % 3)) as $historicalIndex => $historicalMember) {
                $historicalStatuses = [
                    Reservation::STATUS_FULFILLED,
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_EXPIRED,
                ];
                $historicalStatus = $historicalStatuses[($bookIndex + $historicalIndex) % count($historicalStatuses)];

                $historicalReservedAt = $this->coreSafeTimestamp(now()->subDays(12 + (($bookIndex + $historicalIndex) % 14))->subHours(1 + (($bookIndex + $historicalIndex) % 12)));

                $this->coreSeedReservationForBook(
                    $library,
                    $book,
                    $historicalMember,
                    $historicalStatus,
                    $historicalReservedAt,
                    $historicalStatus === Reservation::STATUS_EXPIRED ? now()->subDays(1 + (($bookIndex + $historicalIndex) % 4)) : null,
                    match ($historicalStatus) {
                        Reservation::STATUS_FULFILLED => 'Rezervacija buvo skmingai vykdyta ir knyga atsiimta.',
                        Reservation::STATUS_CANCELLED => 'Narys atauk rezervacij telefonu.',
                        Reservation::STATUS_EXPIRED => 'Narys laiku neatsim knygos.',
                        default => null,
                    }
                );
            }
        }
    }

    private function coreSeedReservationForBook(
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
    private function coreSeedScanLogs(Library $library, Collection $copies, Collection $employees): void
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
    private function coreSeedAuditLogsForLibrary(
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
                $createdAt = $this->coreSafeTimestamp(now()->subDays(40 - ($bookIndex * 8))->addHours($step));

                $this->coreCreateAuditLog([
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
                $createdAt = $this->coreSafeTimestamp(now()->subDays(18 - $copyIndex)->addMinutes($step * 35));

                $this->coreCreateAuditLog([
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
                $createdAt = $this->coreSafeTimestamp(now()->subDays(12 - $memberIndex)->addMinutes($step * 50));

                $this->coreCreateAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 2 === 0 ? 'user_updated' : 'loan_issued',
                    'auditable_type' => $member->getMorphClass(),
                    'auditable_id' => $member->id,
                    'description' => $step % 2 === 0
                        ? sprintf('Atnaujintas vartotojas "%s".', $member->name)
                        : sprintf('Knyga iduota vartotojui "%s".', $member->name),
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
    private function coreSeedNotificationCatalogForEglePetrauskaite(User $member, User $sender, Library $library, Collection $books): void
    {
        $member->notifications()
            ->get()
            ->filter(fn ($notification) => (bool) data_get($notification->data, 'metadata.demo_notification_catalog'))
            ->each->delete();

        $bookTitle = $books->first()?->title ?: 'Demo knyga';
        $notificationDefinitions = [
            NotificationType::RESERVATION_CREATED->value => [
                'title' => 'Rezervacija sukurta',
                'message' => sprintf('Js skmingai rezervavote knyg "%s". Js vieta eilje: 1.', $bookTitle),
            ],
            NotificationType::RESERVATION_QUEUE_CHANGED->value => [
                'title' => 'Rezervacijos eil pasikeit',
                'message' => sprintf('Knygos "%s" rezervacijos eilje dabar esate 1 vietoje.', $bookTitle),
            ],
            NotificationType::RESERVATION_READY->value => [
                'title' => 'Rezervacija paruota',
                'message' => sprintf('Knyga "%s" jau laukia js. Atsiimkite iki rytojaus darbo pabaigos.', $bookTitle),
            ],
            NotificationType::RESERVATION_CANCELLED->value => [
                'title' => 'Rezervacija ataukta',
                'message' => sprintf('Js rezervacija knygai "%s" buvo ataukta bibliotekos darbuotojo.', $bookTitle),
            ],
            NotificationType::RESERVATION_EXPIRED->value => [
                'title' => 'Rezervacijos galiojimas baigsi',
                'message' => sprintf('Rezervacijos knygai "%s" atsimimo terminas baigsi.', $bookTitle),
            ],
            NotificationType::RESERVATION_FULFILLED->value => [
                'title' => 'Rezervacija vykdyta',
                'message' => sprintf('Pagal js rezervacij iduota knyga "%s".', $bookTitle),
            ],
            NotificationType::LOAN_OVERDUE->value => [
                'title' => 'Vluojate grinti knyg',
                'message' => sprintf('Knygos "%s" grinimo terminas jau prajo. Praome susisiekti su biblioteka.', $bookTitle),
            ],
            NotificationType::BOOK_DUE_SOON->value => [
                'title' => 'Artja grinimo terminas',
                'message' => sprintf('Knyg "%s" reiks grinti per artimiausias 2 dienas.', $bookTitle),
            ],
            NotificationType::BOOK_RETURNED->value => [
                'title' => 'Knyga grąžinta',
                'message' => sprintf('Knyga "%s" sėkmingai grąžinta. Ačiū, kad naudojatės biblioteka.', $bookTitle),
            ],
            NotificationType::LIBRARY_MEMBERSHIP_ADDED->value => [
                'title' => 'Pridta bibliotekos naryst',
                'message' => sprintf('Js buvote pridta prie bibliotekos "%s".', $library->name),
            ],
            NotificationType::SYSTEM->value => [
                'title' => 'Sistemos praneimas',
                'message' => 'Bibliotekos sistema atnaujino js paskyros informacij.',
            ],
            NotificationType::NEW_USER->value => [
                'title' => 'Paskyra aktyvuota',
                'message' => 'Js skaitytojo paskyra aktyvuota ir paruota naudojimui.',
            ],
            NotificationType::QR_SCAN->value => [
                'title' => 'QR kodas nuskaitytas',
                'message' => 'Js skaitytojo QR kodas skmingai nuskaitytas bibliotekoje.',
            ],
            NotificationType::REPORT_READY->value => [
                'title' => 'Ataskaita paruota',
                'message' => 'Js prayta bibliotekos ataskaita paruota perirai.',
            ],
            NotificationType::ISSUANCE_SUMMARY->value => [
                'title' => 'Idavimo suvestin',
                'message' => 'Paruota nauja js iduot ir grint knyg suvestin.',
            ],
            NotificationType::SYSTEM_WARNING->value => [
                'title' => 'Sistemos spjimas',
                'message' => 'Sistemai reikalingas js dmesys: patikrinkite paskyros duomenis.',
            ],
            NotificationType::SYSTEM_ERROR->value => [
                'title' => 'Sistemos klaida',
                'message' => 'Nepavyko atlikti vieno veiksmo. Bandykite dar kart arba kreipkits  bibliotek.',
            ],
            NotificationType::ACCOUNT_SECURITY->value => [
                'title' => 'Paskyros saugumas',
                'message' => 'Ufiksuotas naujas prisijungimas prie js paskyros.',
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

    private function coreCreateAuditLog(array $attributes, CarbonInterface $createdAt): void
    {
        $log = AuditLog::create($attributes);
        $log->timestamps = false;
        $log->created_at = $createdAt;
        $log->updated_at = $createdAt;
        $log->save();
    }

    private function coreCopyNotesForStatus(string $status): ?string
    {
        return match ($status) {
            BookCopy::STATUS_AVAILABLE => [
                null,
                'Kopija tvarkinga ir prieinama skaitytojams.',
                'Pastaruoju metu danai iekoma prie informacijos stalo.',
            ][strlen($status) % 3],
            BookCopy::STATUS_LOANED => 'Kopija šiuo metu išduota skaitytojui.',
            BookCopy::STATUS_MAINTENANCE => 'Laukiama smulkaus taisymo arba perklijavimo.',
            BookCopy::STATUS_LOST => 'Nepavyko rasti per paskutin inventorizacij.',
            BookCopy::STATUS_WITHDRAWN => 'Kopija nebepriklauso aktyviam bibliotekos fondui.',
            default => null,
        };
    }

    private function coreRecordCopyHistory(BookCopy $copy, ?User $user, string $reasonCode, string $toStatus, ?string $notes = null): void
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

        $changedAt = $this->coreSafeTimestamp($changedAt);

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

    private function coreSafeTimestamp(CarbonInterface $timestamp): CarbonInterface
    {
        $safe = $timestamp instanceof Carbon
            ? $timestamp->copy()
            : Carbon::instance($timestamp);

        if ((int) $safe->format('H') === 3) {
            return $safe->setTime(4, 0);
        }

        return $safe;
    }

    private function coreUniqueCategorySlug(string $name): string
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

    private function coreUniqueAuthorSlug(string $name): string
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

    private function kaltSeedKaltinenaiLibraryScenarios(): void
    {
        $this->guardDemoSeedingIsAllowed();

        DB::transaction(function () {
            $now = now()->toImmutable();

            $library = Library::query()->updateOrCreate(
                ['code' => 'KALT-ASTU-001'],
                [
                    'name' => 'Kaltinėnų A. Stulginskio biblioteka',
                    'email' => 'info@kaltinenubiblioteka.lt',
                    'phone' => '+37060000123',
                    'address' => 'Varnių g. 12',
                    'city' => 'Kaltinėnai',
                    'is_active' => true,
                    'is_public' => true,
                ]
            );

            $mainBranch = Branch::query()->updateOrCreate(
                ['library_id' => $library->id, 'code' => 'MAIN'],
                [
                    'name' => 'Pagrindinis skyrius',
                    'address' => 'Varnių g. 12',
                    'city' => 'Kaltinėnai',
                ]
            );

            $childrenBranch = Branch::query()->updateOrCreate(
                ['library_id' => $library->id, 'code' => 'KIDS'],
                [
                    'name' => 'Vaikų ir jaunimo skyrius',
                    'address' => 'Varnių g. 12',
                    'city' => 'Kaltinėnai',
                ]
            );

            $actors = (new DemoAccessActorSynchronizer())->syncLibrary($library);

            if (
                Loan::query()->where('library_id', $library->id)->exists()
                || Reservation::query()->where('library_id', $library->id)->exists()
            ) {
                return;
            }

            $fantasyLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $mainBranch->id,
                'name' => 'Fantastikos lentyna',
                'code' => 'LOC-FAN-01',
                'room' => '1',
                'shelf' => 'A-1',
                'description' => 'Fantastikos ir nuotykių knygos',
            ]);

            $classicLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $mainBranch->id,
                'name' => 'Klasikos lentyna',
                'code' => 'LOC-KLAS-01',
                'room' => '1',
                'shelf' => 'B-2',
                'description' => 'Lietuvių ir pasaulio klasika',
            ]);

            $childrenLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $childrenBranch->id,
                'name' => 'Jaunimo lentyna',
                'code' => 'LOC-YA-01',
                'room' => '2',
                'shelf' => 'C-3',
                'description' => 'Paauglių ir jaunimo literatūra',
            ]);

            $fictionCategory = Category::query()->firstOrCreate(
                ['name' => 'Grožinė literatūra'],
                ['slug' => GeneratesSlugs::from('Grožinė literatūra', 'kategorija'), 'description' => 'Romanai, apsakymai ir kita grožinė literatūra.']
            );
            $fantasyCategory = Category::query()->firstOrCreate(
                ['name' => 'Fantastika'],
                ['slug' => GeneratesSlugs::from('Fantastika', 'kategorija'), 'description' => 'Fantastinė, maginė ir nuotykių literatūra.']
            );
            $classicCategory = Category::query()->firstOrCreate(
                ['name' => 'Klasika'],
                ['slug' => GeneratesSlugs::from('Klasika', 'kategorija'), 'description' => 'Lietuvių ir pasaulio literatūros klasika.']
            );
            $youthCategory = Category::query()->firstOrCreate(
                ['name' => 'Jaunimo literatūra'],
                ['slug' => GeneratesSlugs::from('Jaunimo literatūra', 'kategorija'), 'description' => 'Knygos jauniesiems skaitytojams.']
            );

            $almaLittera = Publisher::query()->firstOrCreate(['name' => 'Alma littera'], ['country' => 'Lietuva']);
            $eridanas = Publisher::query()->firstOrCreate(['name' => 'Eridanas'], ['country' => 'Lietuva']);
            $vaga = Publisher::query()->firstOrCreate(['name' => 'Vaga'], ['country' => 'Lietuva']);
            $tytoAlba = Publisher::query()->firstOrCreate(['name' => 'Tyto alba'], ['country' => 'Lietuva']);

            $rowling = Author::query()->firstOrCreate(['name' => 'J. K. Rowling'], ['bio' => 'Britų rašytoja, geriausiai žinoma dėl Hario Poterio serijos.']);
            $sapkowski = Author::query()->firstOrCreate(['name' => 'Andrzej Sapkowski'], ['bio' => 'Lenkų fantastikos rašytojas, išgarsinęs Raganiaus ciklą.']);
            $putinas = Author::query()->firstOrCreate(['name' => 'Vincas Mykolaitis-Putinas'], ['bio' => 'Lietuvių rašytojas, poetas ir literatūros istorikas.']);
            $sruoga = Author::query()->firstOrCreate(['name' => 'Balys Sruoga'], ['bio' => 'Lietuvių rašytojas, dramaturgas ir literatūros kritikas.']);
            $green = Author::query()->firstOrCreate(['name' => 'John Green'], ['bio' => 'Amerikiečių jaunimo literatūros autorius.']);

            $admin = $actors['admins']->first();
            $staffA = $actors['staff']->firstWhere('email', 'ieva@kaltinenubiblioteka.lt');
            $staffB = $actors['staff']->firstWhere('email', 'tomas@kaltinenubiblioteka.lt');
            $member1 = $actors['members']->firstWhere('email', 'lukas.skaitytojas@example.com');
            $member3 = $actors['members']->firstWhere('email', 'matas.skaitytojas@example.com');
            $member4 = $actors['members']->firstWhere('email', 'gabija.skaitytoja@example.com');
            $member5 = $actors['members']->firstWhere('email', 'saule.skaitytoja@example.com');
            $allMembers = $actors['members']->values();
            $employees = $actors['admins']->merge($actors['staff'])->values();

            if (
                Loan::query()->where('library_id', $library->id)->exists()
                || Reservation::query()->where('library_id', $library->id)->exists()
            ) {
                return;
            }

            $hp1 = Book::query()->where('isbn', '9786090141601')->firstOrFail();
            $witcher = Book::query()->where('isbn', '9786090404256')->firstOrFail();
            $altoriu = Book::query()->where('isbn', '9799955000357')->firstOrFail();
            $dievuMiskas = Book::query()->where('isbn', '9786090000001')->firstOrFail();
            $faultInOurStars = Book::query()->where('isbn', '9786094799716')->firstOrFail();

            $orwell = Author::query()->firstOrCreate(['name' => 'George Orwell'], ['bio' => 'Anglų rašytojas, žinomas dėl distopinių romanų.']);
            $tolkien = Author::query()->firstOrCreate(['name' => 'J. R. R. Tolkien'], ['bio' => 'Britų fantastas, sukūręs Viduržemės pasaulį.']);
            $clear = Author::query()->firstOrCreate(['name' => 'James Clear'], ['bio' => 'Autorius, rašantis apie įpročius ir kasdienius pokyčius.']);
            $kahneman = Author::query()->firstOrCreate(['name' => 'Daniel Kahneman'], ['bio' => 'Psichologas ir Nobelio premijos laureatas.']);
            $saintExupery = Author::query()->firstOrCreate(['name' => 'Antoine de Saint-Exupéry'], ['bio' => 'Prancūzų autorius, parašęs Mažąjį princą.']);

            $psychologyCategory = Category::query()->firstOrCreate(
                ['name' => 'Psichologija'],
                ['slug' => GeneratesSlugs::from('Psichologija', 'kategorija'), 'description' => 'Psichologijos ir saviugdos knygos.']
            );
            $romanCategory = Category::query()->firstOrCreate(
                ['name' => 'Romanai'],
                ['slug' => GeneratesSlugs::from('Romanai', 'kategorija'), 'description' => 'Grožinės literatūros romanai.']
            );

            $book1984 = Book::query()->where('isbn', '9786090900499')->firstOrFail();
            $hobbit = Book::query()->where('isbn', '9786090158005')->firstOrFail();
            $atomicHabits = Book::query()->where('isbn', '9786090152683')->firstOrFail();
            $thinking = Book::query()->where('isbn', '9785415020980')->firstOrFail();
            $littlePrince = Book::query()->where('isbn', '9786090141564')->firstOrFail();

            $copies = collect([
                $this->kaltCreateCopy($library, $hp1, $mainBranch, $fantasyLocation, 'KAL-HP1-001', 'QR-KAL-HP1-001', '9786090141601', BookCopy::STATUS_LOANED, BookCopy::CONDITION_GOOD, '2023-09-01', 'Dažnai skolinama knyga.'),
                $this->kaltCreateCopy($library, $hp1, $mainBranch, $fantasyLocation, 'KAL-HP1-002', 'QR-KAL-HP1-002', '9786090141602', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2023-09-01', null),
                $this->kaltCreateCopy($library, $witcher, $mainBranch, $fantasyLocation, 'KAL-RAG-001', 'QR-KAL-RAG-001', '9786090404251', BookCopy::STATUS_MAINTENANCE, BookCopy::CONDITION_DAMAGED, '2024-01-15', 'Lūžta nugarėlė, išsiųsta tvarkymui.'),
                $this->kaltCreateCopy($library, $witcher, $mainBranch, $fantasyLocation, 'KAL-RAG-002', 'QR-KAL-RAG-002', '9786090404252', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2024-01-15', null),
                $this->kaltCreateCopy($library, $altoriu, $mainBranch, $classicLocation, 'KAL-ALT-001', 'QR-KAL-ALT-001', '9799955000351', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_DAMAGED, '2021-11-20', 'Apiplyšęs viršelis.'),
                $this->kaltCreateCopy($library, $altoriu, $mainBranch, $classicLocation, 'KAL-ALT-002', 'QR-KAL-ALT-002', '9799955000352', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_WORN, '2019-03-14', 'Senesnis kopija.'),
                $this->kaltCreateCopy($library, $dievuMiskas, $mainBranch, $classicLocation, 'KAL-DM-001', 'QR-KAL-DM-001', 'KAL-PRV-001', BookCopy::STATUS_LOST, BookCopy::CONDITION_GOOD, '2020-10-01', 'Nerastas po inventorizacijos.'),
                $this->kaltCreateCopy($library, $faultInOurStars, $childrenBranch, $childrenLocation, 'KAL-YA-001', 'QR-KAL-YA-001', '9786094799711', BookCopy::STATUS_WITHDRAWN, BookCopy::CONDITION_WORN, '2018-04-04', 'Per daug susidėvėjęs, nurašytas.'),
                $this->kaltCreateCopy($library, $faultInOurStars, $childrenBranch, $childrenLocation, 'KAL-YA-002', 'QR-KAL-YA-002', '9786094799712', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2024-02-10', 'Laisva kopija, skirta greitam išdavimui rezervacijos eilėje.'),
                $this->kaltCreateCopy($library, $book1984, $mainBranch, $classicLocation, 'KAL-1984-001', 'QR-KAL-1984-001', '9786090900491', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2023-01-15', null),
                $this->kaltCreateCopy($library, $book1984, $mainBranch, $classicLocation, 'KAL-1984-002', 'QR-KAL-1984-002', '9786090900492', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2023-01-15', null),
                $this->kaltCreateCopy($library, $hobbit, $mainBranch, $fantasyLocation, 'KAL-HOB-001', 'QR-KAL-HOB-001', '9786090158001', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2022-08-20', null),
                $this->kaltCreateCopy($library, $hobbit, $mainBranch, $fantasyLocation, 'KAL-HOB-002', 'QR-KAL-HOB-002', '9786090158002', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_WORN, '2022-08-20', null),
                $this->kaltCreateCopy($library, $atomicHabits, $mainBranch, $classicLocation, 'KAL-AH-001', 'QR-KAL-AH-001', '9786090152681', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2024-06-10', null),
                $this->kaltCreateCopy($library, $thinking, $mainBranch, $classicLocation, 'KAL-TF-001', 'QR-KAL-TF-001', '9785415020981', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2024-04-08', null),
                $this->kaltCreateCopy($library, $littlePrince, $childrenBranch, $childrenLocation, 'KAL-MP-001', 'QR-KAL-MP-001', '9786090141561', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_GOOD, '2023-11-09', null),
                $this->kaltCreateCopy($library, $littlePrince, $childrenBranch, $childrenLocation, 'KAL-MP-002', 'QR-KAL-MP-002', '9786090141562', BookCopy::STATUS_AVAILABLE, BookCopy::CONDITION_WORN, '2023-11-09', null),
            ]);

            $loanA = Loan::create([
                'library_id' => $library->id,
                'book_copy_id' => $copies[0]->id,
                'user_id' => $member1->id,
                'issued_by' => $staffA->id,
                'received_by' => null,
                'borrowed_at' => $now->subHours(2),
                'due_at' => $now->addDays(14),
                'returned_at' => null,
                'status' => Loan::STATUS_ACTIVE,
                'renewal_count' => 0,
                'notes' => 'Skolinimas testavimui per mobili programl.',
            ]);

            $loanB = Loan::create([
                'library_id' => $library->id,
                'book_copy_id' => $copies[5]->id,
                'user_id' => $member3->id,
                'issued_by' => $staffB->id,
                'received_by' => $staffA->id,
                'borrowed_at' => $now->subDays(20),
                'due_at' => $now->subDays(6),
                'returned_at' => $now->subDays(5),
                'status' => Loan::STATUS_RETURNED,
                'renewal_count' => 1,
                'notes' => 'Jau grąžinta demonstracinė paskola.',
            ]);

            Reservation::create([
                'library_id' => $library->id,
                'book_id' => $faultInOurStars->id,
                'user_id' => $member4->id,
                'status' => Reservation::STATUS_READY,
                'pickup_branch_id' => $childrenBranch->id,
                'assigned_book_copy_id' => $copies[8]->id,
                'reserved_at' => $now->subHours(3),
                'ready_at' => $now->subHours(2),
                'expires_at' => $now->addDays(3),
                'fulfilled_at' => null,
                'cancelled_at' => null,
                'notes' => 'Laukia laisvos kopijos jaunimo skyriuje.',
            ]);

            Reservation::create([
                'library_id' => $library->id,
                'book_id' => $witcher->id,
                'user_id' => $member5->id,
                'status' => Reservation::STATUS_CANCELLED,
                'reserved_at' => $now->subDays(1),
                'expires_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => $now,
                'notes' => 'Atšaukta demonstracinė rezervacija.',
            ]);

            $this->kaltRecordHistory($copies[0], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-05-12 09:00:00'));
            $this->kaltRecordHistory($copies[0], $staffA, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOANED, 'issued', 'Kopija šiandien išduota skaitytojui.', $now->subHours(2));
            $this->kaltRecordHistory($copies[2], $staffB, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-08-14 10:00:00'));
            $this->kaltRecordHistory($copies[2], $staffB, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_MAINTENANCE, 'sent_to_maintenance', 'Išsiųstas tvarkyti dėl pažeidimų.', $now->subDays(11));
            $this->kaltRecordHistory($copies[4], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-07-03 14:00:00'));
            $this->kaltRecordHistory($copies[4], $staffA, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_AVAILABLE, 'marked_damaged', 'Apžiūros metu pažymėta fizinė būklė: sugadinta.', $now->subMonths(2)->subDays(4));
            $this->kaltRecordHistory($copies[6], $admin, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-06-06 11:00:00'));
            $this->kaltRecordHistory($copies[6], $admin, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOST, 'marked_lost', 'Inventorizacijos metu kopija nerasta.', $now->subMonths(5));
            $this->kaltRecordHistory($copies[7], $admin, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-05-28 12:30:00'));
            $this->kaltRecordHistory($copies[7], $admin, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_WITHDRAWN, 'nurašyta', 'Nurašyta dėl susidėvėjimo.', $now->subMonths(7));
            $this->kaltRecordHistory($copies[8], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Kopija sukurta sistemoje.', CarbonImmutable::parse('2025-09-01 09:15:00'));

            $this->kaltSeedHistoricalLoans($library, $copies->slice(9)->values(), $allMembers, $employees, $now);
            $this->kaltSeedHistoricalReservations($library, collect([
                $hp1,
                $witcher,
                $altoriu,
                $dievuMiskas,
                $faultInOurStars,
                $book1984,
                $hobbit,
                $atomicHabits,
                $thinking,
                $littlePrince,
            ]), $allMembers, $now);

            ScanLog::insert([
                [
                    'library_id' => $library->id,
                    'book_copy_id' => $copies[0]->id,
                    'user_id' => $staffA->id,
                    'scan_value' => $copies[0]->qr_code,
                    'scan_type' => 'loan',
                    'result' => 'success',
                    'device_info' => 'Samsung A52',
                    'created_at' => $now->subHours(2),
                    'updated_at' => $now->subHours(2),
                ],
                [
                    'library_id' => $library->id,
                    'book_copy_id' => $copies[2]->id,
                    'user_id' => $staffB->id,
                    'scan_value' => $copies[2]->qr_code,
                    'scan_type' => 'inventory',
                    'result' => 'success',
                    'device_info' => 'Samsung A55',
                    'created_at' => $now->subDays(11),
                    'updated_at' => $now->subDays(11),
                ],
                [
                    'library_id' => $library->id,
                    'book_copy_id' => $copies[6]->id,
                    'user_id' => $admin->id,
                    'scan_value' => $copies[6]->qr_code,
                    'scan_type' => 'inventory',
                    'result' => 'error',
                    'device_info' => 'Web Scanner',
                    'created_at' => $now->subMonths(5),
                    'updated_at' => $now->subMonths(5),
                ],
            ]);
        });
    }

    private function kaltCreateCopy(
        Library $library,
        Book $book,
        Branch $branch,
        Location $location,
        string $inventoryCode,
        string $qrCode,
        string $barcode,
        string $status,
        string $condition,
        string $acquiredAt,
        ?string $notes
    ): BookCopy {
        $copy = BookCopy::create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'inventory_code' => $inventoryCode,
            'qr_code' => $qrCode,
            'barcode' => $barcode,
            'status' => $status,
            'condition_status' => $condition,
            'acquired_at' => $acquiredAt,
            'notes' => $notes,
        ]);

        $createdAt = CarbonImmutable::parse($acquiredAt)->startOfDay()->addHours(9);
        $copy->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $copy;
    }

    private function kaltRecordHistory(
        BookCopy $copy,
        ?User $user,
        ?string $fromStatus,
        string $toStatus,
        string $reasonCode,
        string $reasonNotes,
        ?CarbonImmutable $changedAt = null
    ): void {
        BookCopyStatusHistory::create([
            'book_copy_id' => $copy->id,
            'changed_by' => $user?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
            'reason_notes' => $reasonNotes,
            'changed_at' => $changedAt ?? now()->subDays(7),
        ]);

        if ($changedAt && $changedAt->gt($copy->updated_at?->toImmutable() ?? CarbonImmutable::parse($copy->created_at))) {
            $copy->forceFill([
                'updated_at' => $changedAt,
            ])->saveQuietly();
        }
    }

    private function kaltSeedHistoricalLoans(Library $library, $copies, $members, $employees, CarbonImmutable $now): void
    {
        if ($copies->isEmpty()) {
            return;
        }

        foreach (range(1, 12) as $monthOffset) {
            $copy = $copies[($monthOffset - 1) % $copies->count()];
            $member = $members[($monthOffset - 1) % $members->count()];
            $employee = $employees[($monthOffset - 1) % $employees->count()];

            $borrowedAt = $now->subMonths($monthOffset)->setTime(10, 0)->subDays(($monthOffset % 3) + 1);
            $dueAt = $borrowedAt->addDays(14);
            $returnedAt = $borrowedAt->addDays(10 + ($monthOffset % 5));

            Loan::create([
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $member->id,
                'issued_by' => $employee->id,
                'received_by' => $employee->id,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => $returnedAt,
                'status' => Loan::STATUS_RETURNED,
                'renewal_count' => $monthOffset % 2,
                'notes' => 'Istorinis demonstracinis išdavimas testavimui.',
            ]);

            $this->kaltRecordHistory($copy, $employee, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOANED, 'issued', 'Istorinis išdavimas demonstraciniams duomenims.', $borrowedAt);
            $this->kaltRecordHistory($copy, $employee, BookCopy::STATUS_LOANED, BookCopy::STATUS_AVAILABLE, 'grąžinta', 'Istorinis grąžinimas demonstraciniams duomenims.', $returnedAt);

            $copy->forceFill([
                'status' => BookCopy::STATUS_AVAILABLE,
                'updated_at' => $returnedAt,
            ])->saveQuietly();
        }
    }

    private function kaltSeedHistoricalReservations(Library $library, $books, $members, CarbonImmutable $now): void
    {
        foreach (range(1, 12) as $monthOffset) {
            $book = $books[($monthOffset - 1) % $books->count()];
            $member = $members[($monthOffset - 1) % $members->count()];
            $reservedAt = $now->subMonths($monthOffset)->setTime(9, 30)->subDays($monthOffset % 4);

            $status = match ($monthOffset % 4) {
                0 => Reservation::STATUS_FULFILLED,
                1 => Reservation::STATUS_CANCELLED,
                2 => Reservation::STATUS_EXPIRED,
                default => Reservation::STATUS_WAITING,
            };

            Reservation::create([
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $member->id,
                'status' => $status,
                'reserved_at' => $reservedAt,
                'ready_at' => $status === Reservation::STATUS_READY ? $reservedAt : null,
                'expires_at' => in_array($status, [Reservation::STATUS_READY, Reservation::STATUS_EXPIRED], true)
                    ? $reservedAt->addDays(5)
                    : null,
                'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? $reservedAt->addDays(2) : null,
                'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? $reservedAt->addDay() : null,
                'notes' => 'Istorin rezervacija dashboard testavimui.',
            ]);
        }
    }

    private const PRESENTATION_PREFIX = 'PRES';

    private const PRESENTATION_TARGET_BRANCHES = 8;

    private const PRESENTATION_TARGET_LOCATIONS = 120;

    private const PRESENTATION_TARGET_CATEGORIES = 42;

    private const PRESENTATION_TARGET_PUBLISHERS = 75;

    private const PRESENTATION_TARGET_AUTHORS = 300;

    private const PRESENTATION_TARGET_BOOKS = 1200;

    private const PRESENTATION_TARGET_COPIES = 3600;

    private const PRESENTATION_TARGET_MEMBERS = 650;

    private const PRESENTATION_TARGET_STAFF = 18;

    private const PRESENTATION_TARGET_LOANS = 3600;

    private const PRESENTATION_TARGET_ACTIVE_LOANS = 500;

    private const PRESENTATION_TARGET_OVERDUE_LOANS = 120;

    private const PRESENTATION_TARGET_RESERVATIONS = 1500;

    private const PRESENTATION_TARGET_SCAN_LOGS = 5500;

    private const PRESENTATION_TARGET_AUDIT_LOGS = 11000;

    private const PRESENTATION_TARGET_NOTIFICATIONS = 5500;

    private const PRESENTATION_DATASET_VERSION = '1';

    private function presentationSeedPresentationDataset(): void
    {
        $this->guardDemoSeedingIsAllowed();

        $libraryCode = config('demo.presentation.library_code', 'LIB-X');
        $library = Library::query()->where('code', $libraryCode)->first();

        if (! $library) {
            $this->command?->error('Presentation demo library "'.$libraryCode.'" was not found. Run the base demo seed first or create the target library.');

            return;
        }

        $branches = $this->presentationEnsureBranches($library);
        (new DemoAccessActorSynchronizer())->syncLibrary($library);

        $locations = $this->presentationEnsureLocations($library, $branches);
        $categories = $this->presentationEnsureCategories();
        $publishers = $this->presentationEnsurePublishers();
        $authors = $this->presentationEnsureAuthors();
        $books = $this->presentationEnsureBooks($categories, $publishers, $authors);
        $staff = $this->presentationEnsureStaff($library, $branches);
        $members = $this->presentationEnsureMembers($library);
        $copies = $this->presentationEnsureCopies($library, $books, $branches, $locations, $staff);

        if (! $this->presentationDatasetCompleted($library)) {
            $this->presentationEnsureLoans($library, $copies, $members, $staff);
            $this->presentationEnsureReservations($library, $books, $branches, $members);
            $this->presentationEnsureScanLogs($library, $copies, $staff);
            $this->presentationEnsureAuditLogs($library, $books, $copies, $members, $staff);
            $this->presentationEnsureNotifications($library, $books, $members, $staff);
            $this->presentationMarkDatasetCompleted($library);
        }

        $this->presentationPrintReport($library->refresh());
    }

    /**
     * @return Collection<int, Branch>
     */
    private function presentationEnsureBranches(Library $library): Collection
    {
        $definitions = [
            ['Centrinė biblioteka', 'Gedimino pr. 12', 'Vilnius'],
            ['Vaikų skyrius', 'Trakų g. 8', 'Vilnius'],
            ['Jaunimo skyrius', 'Pylimo g. 21', 'Vilnius'],
            ['Technikos skyrius', 'Konstitucijos pr. 14', 'Vilnius'],
            ['Istorijos skyrius', 'Didžioji g. 5', 'Vilnius'],
            ['Meno skyrius', 'Maironio g. 10', 'Vilnius'],
            ['Mokslinė skaitykla', 'Universiteto g. 3', 'Vilnius'],
            ['Regioninis skyrius', 'Ukmergės g. 120', 'Vilnius'],
        ];

        foreach ($definitions as $index => [$name, $address, $city]) {
            Branch::query()->updateOrCreate(
                ['library_id' => $library->id, 'code' => self::PRESENTATION_PREFIX.'-B'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                ['name' => $name, 'address' => $address, 'city' => $city]
            );
        }

        return Branch::query()
            ->where('library_id', $library->id)
            ->orderByRaw("code like '".self::PRESENTATION_PREFIX."-%' desc")
            ->orderBy('id')
            ->take(max(self::PRESENTATION_TARGET_BRANCHES, Branch::query()->where('library_id', $library->id)->count()))
            ->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, Location>
     */
    private function presentationEnsureLocations(Library $library, Collection $branches): Collection
    {
        $names = [
            'Skaitykla A', 'Skaitykla B', 'Lentyna A1', 'Lentyna A2', 'Lentyna B1',
            'Lentyna B2', 'Lentyna C1', 'Archyvas', 'Sandėlys', 'Naujos knygos',
            'Retų leidinių fondas', 'Periodikos zona', 'Vaikų kampas', 'Kompiuterių salė',
            'Tyrimų stalai',
        ];

        $perBranch = (int) ceil(self::PRESENTATION_TARGET_LOCATIONS / max(1, $branches->count()));

        foreach ($branches as $branchIndex => $branch) {
            for ($i = 1; $i <= $perBranch; $i++) {
                $code = self::PRESENTATION_PREFIX.'-L'.str_pad((string) $branch->id, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $name = $names[($i - 1) % count($names)];

                Location::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'code' => $code],
                    [
                        'library_id' => $library->id,
                        'name' => $name,
                        'room' => (string) (($branchIndex % 4) + 1),
                        'shelf' => chr(65 + (($i - 1) % 8)).'-'.(($i % 12) + 1),
                        'description' => $name.' vieta demonstraciniam bibliotekos fondui.',
                    ]
                );
            }
        }

        return Location::query()->where('library_id', $library->id)->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function presentationEnsureCategories(): Collection
    {
        $names = [
            'Fantastika', 'Mokslinė fantastika', 'Romanai', 'Istorija', 'Biografijos',
            'IT', 'Programavimas', 'Duomenų bazės', 'Tinklai', 'Dirbtinis intelektas',
            'Psichologija', 'Medicina', 'Teisė', 'Ekonomika', 'Finansai', 'Religija',
            'Menas', 'Muzika', 'Poezija', 'Vaikų literatūra', 'Jaunimo literatūra',
            'Klasika', 'Detektyvai', 'Trileriai', 'Kelionės', 'Verslas', 'Vadyba',
            'Marketingas', 'Pedagogika', 'Filosofija', 'Sociologija', 'Politika',
            'Gamtos mokslai', 'Matematika', 'Fizika', 'Chemija', 'Inžinerija',
            'Architektūra', 'Kulinarija', 'Sveikata', 'Sportas', 'Lietuvių literatūra',
        ];

        foreach (array_slice($names, 0, self::PRESENTATION_TARGET_CATEGORIES) as $name) {
            Category::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => $this->presentationUniqueSlug(Category::class, $name, 'kategorija'),
                    'description' => $name.' kategorija demonstraciniam katalogui.',
                ]
            );
        }

        return Category::query()->whereIn('name', array_slice($names, 0, self::PRESENTATION_TARGET_CATEGORIES))->get();
    }

    /**
     * @return Collection<int, Publisher>
     */
    private function presentationEnsurePublishers(): Collection
    {
        $countries = ['Lietuva', 'Latvija', 'Estija', 'Lenkija', 'Vokietija', 'Prancūzija', 'Jungtinė Karalystė', 'JAV'];
        $baseNames = [
            'Alma littera', 'Tyto alba', 'Vaga', 'Baltos lankos', 'Sofoklis', 'Kitos knygos',
            'Nieko rimto', 'Penguin Books', 'Random House', 'Springer', 'O Reilly Media',
            'No Starch Press', 'MIT Press', 'Oxford University Press', 'Cambridge Press',
        ];

        for ($i = 1; $i <= self::PRESENTATION_TARGET_PUBLISHERS; $i++) {
            $name = $baseNames[$i - 1] ?? self::PRESENTATION_PREFIX.' Leidykla '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            Publisher::query()->firstOrCreate(
                ['name' => $name],
                ['country' => $countries[($i - 1) % count($countries)]]
            );
        }

        return Publisher::query()
            ->where(function ($query): void {
                $query->where('name', 'like', self::PRESENTATION_PREFIX.' Leidykla %')
                    ->orWhereIn('name', [
                        'Alma littera', 'Tyto alba', 'Vaga', 'Baltos lankos', 'Sofoklis', 'Kitos knygos',
                        'Nieko rimto', 'Penguin Books', 'Random House', 'Springer', 'O Reilly Media',
                        'No Starch Press', 'MIT Press', 'Oxford University Press', 'Cambridge Press',
                    ]);
            })
            ->get();
    }

    /**
     * @return Collection<int, Author>
     */
    private function presentationEnsureAuthors(): Collection
    {
        $firstNames = ['Jonas', 'Ieva', 'Mantas', 'Rasa', 'Lina', 'Tomas', 'Austėja', 'Darius', 'Greta', 'Nojus', 'Emily', 'James', 'Anna', 'Mark', 'Sofia', 'Lucas', 'Marie', 'Thomas', 'Elena', 'Nicolas'];
        $lastNames = ['Petrauskas', 'Kazlauskaitė', 'Jankauskas', 'Vaitkus', 'Žukauskas', 'Kavaliauskas', 'Smith', 'Johnson', 'Brown', 'Miller', 'Dubois', 'Muller', 'Rossi', 'Garcia', 'Nowak'];

        for ($i = 1; $i <= self::PRESENTATION_TARGET_AUTHORS; $i++) {
            $name = self::PRESENTATION_PREFIX.' Autorius '.$firstNames[($i - 1) % count($firstNames)].' '.$lastNames[($i - 1) % count($lastNames)].' '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            Author::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => $this->presentationUniqueSlug(Author::class, $name, 'autorius'),
                    'bio' => 'Autorius rašo apie kultūrą, visuomenės pokyčius ir šiuolaikinės bibliotekos skaitytojų temas.',
                ]
            );
        }

        return Author::query()->where('name', 'like', self::PRESENTATION_PREFIX.' Autorius %')->get();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Publisher>  $publishers
     * @param  Collection<int, Author>  $authors
     * @return Collection<int, Book>
     */
    private function presentationEnsureBooks(Collection $categories, Collection $publishers, Collection $authors): Collection
    {
        $existing = Book::query()->where('isbn', 'like', '97877%')->count();
        $missing = max(0, self::PRESENTATION_TARGET_BOOKS - $existing);
        $themes = ['Miestas', 'Atmintis', 'Algoritmai', 'Kelionė', 'Sodas', 'Tyrimas', 'Horizontas', 'Pokalbiai', 'Slenkstis', 'Praktika'];

        for ($i = $existing + 1; $i <= $existing + $missing; $i++) {
            $category = $categories[($i - 1) % $categories->count()];
            $publisher = $publishers[($i - 1) % $publishers->count()];
            $title = $themes[($i - 1) % count($themes)].' '.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $isbn = '97877'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);

            $book = Book::query()->firstOrCreate(
                ['isbn' => $isbn],
                [
                    'title' => $title,
                    'subtitle' => 'Demonstracinis leidinys bibliotekos katalogui',
                    'description' => $this->presentationBookDescription($category->name, $title),
                    'publisher_id' => $publisher->id,
                    'category_id' => $category->id,
                    'publication_year' => 1995 + ($i % 31),
                    'language' => ['lt', 'en', 'pl', 'de', 'fr'][$i % 5],
                    'page_count' => 96 + (($i * 17) % 780),
                    'edition' => (($i % 4) + 1).' leidimas',
                    'cover_image' => null,
                ]
            );

            $authorIds = $authors->slice($i % max(1, $authors->count() - 3), 1 + ($i % 3))->pluck('id')->all();
            $book->authors()->syncWithoutDetaching($authorIds);
            $book->categories()->syncWithoutDetaching([$category->id]);
        }

        return Book::query()->where('isbn', 'like', '97877%')->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, User>
     */
    private function presentationEnsureStaff(Library $library, Collection $branches): Collection
    {
        $staff = collect();
        $staffDefinitions = $this->presentationPresentationStaffDefinitions();
        $branchResolver = new DemoAccessActorSynchronizer();

        for ($i = 1; $i <= self::PRESENTATION_TARGET_STAFF; $i++) {
            $email = 'presentation.staff.'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'@example.com';
            $definition = $staffDefinitions[$email] ?? null;

            if (! is_array($definition)) {
                throw new InvalidArgumentException(sprintf(
                    'Presentation demo staff "%s" in library "%s" is missing an explicit branch_code in config("demo.presentation.staff").',
                    $email,
                    $library->code
                ));
            }

            $branch = $branchResolver->resolveStaffBranch($library, $definition + ['email' => $email]);
            $existingMembershipNumber = User::query()->where('email', $email)->value('membership_number');
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo darbuotojas '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_STAFF,
                    'phone' => '+37062'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'membership_number' => $existingMembershipNumber ?: 'MEM:'.(string) Str::ulid(),
                    'is_active' => true,
                ]
            );

            $this->presentationAttachMembership($user, $library, $branch);
            $staff->push($user);
        }

        return $staff->values();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function presentationPresentationStaffDefinitions(): array
    {
        $definitions = config('demo.presentation.staff', []);

        if (! is_array($definitions)) {
            throw new InvalidArgumentException('config("demo.presentation.staff") must be an array of staff definitions.');
        }

        return collect($definitions)
            ->mapWithKeys(function (array $definition) {
                $email = $definition['email'] ?? null;

                if (! is_string($email) || $email === '') {
                    throw new InvalidArgumentException('Each presentation demo staff definition must declare a non-empty email.');
                }

                return [$email => $definition];
            })
            ->all();
    }

    private function presentationDatasetCompleted(Library $library): bool
    {
        return (new DemoDatasetMarker())->completed($library, $this->presentationDatasetKey(), self::PRESENTATION_DATASET_VERSION);
    }

    private function presentationMarkDatasetCompleted(Library $library): void
    {
        (new DemoDatasetMarker())->markCompleted($library, $this->presentationDatasetKey(), self::PRESENTATION_DATASET_VERSION, [
            'target_loans' => self::PRESENTATION_TARGET_LOANS,
            'target_reservations' => self::PRESENTATION_TARGET_RESERVATIONS,
            'target_scan_logs' => self::PRESENTATION_TARGET_SCAN_LOGS,
            'target_audit_logs' => self::PRESENTATION_TARGET_AUDIT_LOGS,
            'target_notifications' => self::PRESENTATION_TARGET_NOTIFICATIONS,
        ]);
    }

    private function presentationDatasetKey(): string
    {
        return config('demo.presentation.dataset_key', 'presentation-demo-v2');
    }

    /**
     * @return Collection<int, User>
     */
    private function presentationEnsureMembers(Library $library): Collection
    {
        $firstNames = ['Aistė', 'Milda', 'Lukas', 'Emilija', 'Rokas', 'Gabija', 'Dovilė', 'Marius', 'Karolina', 'Tadas', 'Saulė', 'Povilas'];
        $lastNames = ['Kazlauskaitė', 'Petrauskas', 'Jankauskas', 'Rimkutė', 'Vaitkus', 'Paulauskas', 'Žukauskaitė', 'Mockus', 'Stankutė', 'Balsevičius'];

        for ($i = 1; $i <= self::PRESENTATION_TARGET_MEMBERS; $i++) {
            $email = 'presentation.member.'.str_pad((string) $i, 4, '0', STR_PAD_LEFT).'@example.com';
            $name = $firstNames[($i - 1) % count($firstNames)].' '.$lastNames[($i - 1) % count($lastNames)].' '.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $existingMembershipNumber = User::query()->where('email', $email)->value('membership_number');

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_MEMBER,
                    'phone' => '+3706'.str_pad((string) (3000000 + $i), 7, '0', STR_PAD_LEFT),
                    'membership_number' => $existingMembershipNumber ?: 'MEM:'.(string) Str::ulid(),
                    'is_active' => true,
                ]
            );

            $this->presentationAttachMembership($user, $library);
        }

        return User::query()
            ->whereIn('id', LibraryMembership::query()->where('library_id', $library->id)->pluck('user_id'))
            ->where('role', User::ROLE_MEMBER)
            ->get();
    }

    private function presentationAttachMembership(User $user, Library $library, ?Branch $branch = null): void
    {
        if ($user->role === User::ROLE_STAFF) {
            if (! $branch) {
                throw new InvalidArgumentException(sprintf(
                    'Demo staff "%s" in library "%s" must be assigned to a declared branch.',
                    $user->email,
                    $library->code
                ));
            }

            if ((int) $branch->library_id !== (int) $library->id) {
                throw new InvalidArgumentException(sprintf(
                    'Demo staff "%s" in library "%s" cannot be assigned to branch "%s" from library id "%s".',
                    $user->email,
                    $library->code,
                    $branch->code,
                    $branch->library_id
                ));
            }

            if (Schema::hasColumn('branches', 'is_active') && ! (bool) $branch->getAttribute('is_active')) {
                throw new InvalidArgumentException(sprintf(
                    'Demo staff "%s" in library "%s" cannot be assigned to inactive branch "%s".',
                    $user->email,
                    $library->code,
                    $branch->code
                ));
            }
        }

        LibraryMembership::query()->updateOrCreate(
            ['library_id' => $library->id, 'user_id' => $user->id],
            [
                'branch_id' => $user->role === User::ROLE_STAFF ? $branch?->id : null,
                'membership_number' => $user->membership_number,
                'is_active' => true,
                'joined_at' => $user->created_at ?? now(),
            ]
        );
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, User>  $staff
     * @return Collection<int, BookCopy>
     */
    private function presentationEnsureCopies(Library $library, Collection $books, Collection $branches, Collection $locations, Collection $staff): Collection
    {
        $existing = BookCopy::query()
            ->where('library_id', $library->id)
            ->count();

        $missing = max(0, self::PRESENTATION_TARGET_COPIES - $existing);
        $rows = [];

        for ($i = $existing + 1; $i <= $existing + $missing; $i++) {
            $book = $books[($i - 1) % $books->count()];
            $branch = $branches[($i - 1) % $branches->count()];
            $branchLocations = $locations->where('branch_id', $branch->id)->values();
            $location = $branchLocations->isNotEmpty() ? $branchLocations[($i - 1) % $branchLocations->count()] : $locations[($i - 1) % $locations->count()];
            $status = $this->presentationCopyStatusForIndex($i);
            $createdAt = $this->presentationSafeTimestamp(now()->subDays(($i * 3) % 720));

            $rows[] = [
                'library_id' => $library->id,
                'book_id' => $book->id,
                'branch_id' => $branch->id,
                'location_id' => $location->id,
                'inventory_code' => $this->demoCopyCodePrefix($library, $branch).'-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'qr_code' => 'QR-'.$this->demoCopyCodePrefix($library, $branch).'-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'barcode' => $this->demoCopyCodePrefix($library, $branch).'-BC-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'condition_status' => BookCopy::conditionValues()[$i % count(BookCopy::conditionValues())],
                'acquired_at' => $createdAt->toDateString(),
                'notes' => $this->presentationCopyNotes($status),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === 500) {
                DB::table('book_copies')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('book_copies')->insertOrIgnore($rows);
        }

        $copies = BookCopy::query()
            ->where('library_id', $library->id)
            ->get();

        $historyRows = [];
        foreach ($copies as $index => $copy) {
            if (BookCopyStatusHistory::query()->where('book_copy_id', $copy->id)->exists()) {
                continue;
            }

            $historyRows[] = [
                'book_copy_id' => $copy->id,
                'changed_by' => $staff[$index % $staff->count()]->id,
                'from_status' => null,
                'to_status' => $copy->status,
                'reason_code' => 'presentation_seed',
                'reason_notes' => 'Kopija sukurta demonstraciniam pristatymui.',
                'changed_at' => $copy->created_at ?? now()->subMonths(12),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($historyRows, 500) as $chunk) {
            DB::table('book_copy_status_histories')->insert($chunk);
        }

        return $copies;
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     * @param  Collection<int, User>  $staff
     */
    private function presentationEnsureLoans(Library $library, Collection $copies, Collection $members, Collection $staff): void
    {
        $currentActive = Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_ACTIVE)->whereNull('returned_at')->count();
        $currentOverdue = Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_OVERDUE)->whereNull('returned_at')->count();
        $currentTotal = Loan::query()->where('library_id', $library->id)->count();

        $activeMissing = max(0, self::PRESENTATION_TARGET_ACTIVE_LOANS - $currentActive);
        $overdueMissing = max(0, self::PRESENTATION_TARGET_OVERDUE_LOANS - $currentOverdue);
        $reservedCopyIds = Loan::query()->where('library_id', $library->id)->whereNull('returned_at')->pluck('book_copy_id')->all();
        $availableCopies = $copies->whereNotIn('id', $reservedCopyIds)->values();

        $this->presentationInsertCurrentLoans($library, $availableCopies->splice(0, $activeMissing), $members, $staff, Loan::STATUS_ACTIVE);
        $this->presentationInsertCurrentLoans($library, $availableCopies->splice(0, $overdueMissing), $members, $staff, Loan::STATUS_OVERDUE);

        $totalAfterCurrent = Loan::query()->where('library_id', $library->id)->count();
        $historicalMissing = max(0, self::PRESENTATION_TARGET_LOANS - $totalAfterCurrent);
        $rows = [];

        for ($i = 1; $i <= $historicalMissing; $i++) {
            $copy = $copies[($i - 1) % $copies->count()];
            $member = $members[($i - 1) % $members->count()];
            $employee = $staff[$i % $staff->count()];
            $borrowedAt = $this->presentationSafeTimestamp(now()->subDays(30 + (($currentTotal + $i) % 700))->setTime(9 + ($i % 8), ($i * 7) % 60));
            $dueAt = $this->presentationSafeTimestamp($borrowedAt->copy()->addDays(14 + ($i % 14)));
            $returnedAt = $this->presentationSafeTimestamp($borrowedAt->copy()->addDays(5 + ($i % 35)));
            $status = $i % 23 === 0 ? Loan::STATUS_LOST : Loan::STATUS_RETURNED;

            $rows[] = [
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $member->id,
                'issued_by' => $employee->id,
                'received_by' => $status === Loan::STATUS_RETURNED ? $staff[($i + 3) % $staff->count()]->id : null,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => $status === Loan::STATUS_RETURNED ? $returnedAt : null,
                'status' => $status,
                'renewal_count' => $i % 3,
                'notes' => 'Istorinė demonstracinė paskola pristatymo statistikoms.',
                'created_at' => $borrowedAt,
                'updated_at' => $status === Loan::STATUS_RETURNED ? $returnedAt : $borrowedAt,
            ];

            if (count($rows) === 500) {
                DB::table('loans')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('loans')->insert($rows);
        }
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     * @param  Collection<int, User>  $staff
     */
    private function presentationInsertCurrentLoans(Library $library, Collection $copies, Collection $members, Collection $staff, string $status): void
    {
        $rows = [];
        $copyIds = [];

        foreach ($copies->values() as $i => $copy) {
            $borrowedAt = $status === Loan::STATUS_OVERDUE
                ? now()->subDays(35 + ($i % 60))->setTime(10, 0)
                : now()->subDays(1 + ($i % 20))->setTime(10, 0);
            $borrowedAt = $this->presentationSafeTimestamp($borrowedAt);
            $dueAt = $status === Loan::STATUS_OVERDUE
                ? now()->subDays(1 + ($i % 25))->setTime(18, 0)
                : now()->addDays(2 + ($i % 21))->setTime(18, 0);
            $dueAt = $this->presentationSafeTimestamp($dueAt);

            $rows[] = [
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $members[$i % $members->count()]->id,
                'issued_by' => $staff[$i % $staff->count()]->id,
                'received_by' => null,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => null,
                'status' => $status,
                'renewal_count' => $i % 2,
                'notes' => $status === Loan::STATUS_OVERDUE ? 'Aktyvi vėluojanti demonstracinė paskola.' : 'Aktyvi demonstracinė paskola.',
                'created_at' => $borrowedAt,
                'updated_at' => $borrowedAt,
            ];
            $copyIds[] = $copy->id;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('loans')->insert($chunk);
        }

        if ($copyIds !== []) {
            BookCopy::query()->whereIn('id', $copyIds)->update(['status' => BookCopy::STATUS_LOANED, 'updated_at' => now()]);
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, User>  $members
     */
    private function presentationEnsureReservations(Library $library, Collection $books, Collection $branches, Collection $members): void
    {
        $currentTotal = Reservation::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::PRESENTATION_TARGET_RESERVATIONS - $currentTotal);
        $popularBooks = $books->take(30)->values();
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $book = $i <= 360 ? $popularBooks[($i - 1) % $popularBooks->count()] : $books[($i - 1) % $books->count()];
            $member = $members[($i - 1) % $members->count()];
            $branch = $branches[$i % $branches->count()];
            $reservedAt = $this->presentationSafeTimestamp(now()->subDays($i % 650)->setTime(8 + ($i % 10), ($i * 11) % 60));
            $status = $this->presentationReservationStatusForIndex($i);
            $expiresAt = in_array($status, [Reservation::STATUS_READY, Reservation::STATUS_EXPIRED], true)
                ? $this->presentationSafeTimestamp($status === Reservation::STATUS_READY ? now()->addDays(1 + ($i % 8)) : $reservedAt->copy()->addDays(5))
                : null;
            $readyAt = $status === Reservation::STATUS_READY ? $reservedAt : null;

            $rows[] = [
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $member->id,
                'scope' => $i % 3 === 0 ? Reservation::SCOPE_BRANCH : Reservation::SCOPE_LIBRARY,
                'branch_id' => $i % 3 === 0 ? $branch->id : null,
                'pickup_branch_id' => $status === Reservation::STATUS_READY ? $branch->id : null,
                'status' => $status,
                'reserved_at' => $reservedAt,
                'ready_at' => $readyAt,
                'expires_at' => $expiresAt,
                'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? $this->presentationSafeTimestamp($reservedAt->copy()->addDays(2)) : null,
                'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? $this->presentationSafeTimestamp($reservedAt->copy()->addDay()) : null,
                'notes' => in_array($status, [Reservation::STATUS_WAITING, Reservation::STATUS_READY], true) ? 'Aktyvi demonstracinė rezervacijos eilė.' : 'Istorinė demonstracinė rezervacija.',
                'created_at' => $reservedAt,
                'updated_at' => $reservedAt,
            ];

            if (count($rows) === 500) {
                DB::table('reservations')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('reservations')->insert($rows);
        }
    }

    /**
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $staff
     */
    private function presentationEnsureScanLogs(Library $library, Collection $copies, Collection $staff): void
    {
        $current = ScanLog::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::PRESENTATION_TARGET_SCAN_LOGS - $current);
        $types = ['info', 'loan', 'return', 'inventory'];
        $results = ['success', 'success', 'success', 'not_found', 'blocked', 'error'];
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $copy = $copies[($i - 1) % $copies->count()];
            $createdAt = $this->presentationSafeTimestamp(now()->subMinutes(($i * 17) % 900000));

            $rows[] = [
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $staff[$i % $staff->count()]->id,
                'scan_value' => $i % 2 === 0 ? $copy->qr_code : (string) $copy->barcode,
                'scan_type' => $types[$i % count($types)],
                'result' => $results[$i % count($results)],
                'device_info' => ['Web scanner', 'Samsung Tab A9', 'iPhone 15', 'Chrome Windows'][$i % 4],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === 500) {
                DB::table('scan_logs')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('scan_logs')->insert($rows);
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     * @param  Collection<int, User>  $staff
     */
    private function presentationEnsureAuditLogs(Library $library, Collection $books, Collection $copies, Collection $members, Collection $staff): void
    {
        $current = AuditLog::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::PRESENTATION_TARGET_AUDIT_LOGS - $current);
        $actions = ['loan_issued', 'loan_returned', 'reservation_created', 'reservation_cancelled', 'book_updated', 'book_copy_status_changed', 'user_updated', 'library_staff_assigned'];
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $action = $actions[$i % count($actions)];
            $createdAt = $this->presentationSafeTimestamp(now()->subMinutes(($i * 37) % 1000000));
            [$auditableType, $auditableId, $label] = $this->presentationAuditTarget($action, $books, $copies, $members, $i);

            $rows[] = [
                'user_id' => $staff[$i % $staff->count()]->id,
                'library_id' => $library->id,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'description' => $this->presentationAuditDescription($action, $label),
                'metadata' => json_encode([
                    'presentation_seed' => true,
                    'auditable_snapshot' => ['label' => $label, 'id' => $auditableId],
                    'request_context' => [
                        'ip' => '127.0.0.'.(($i % 200) + 1),
                        'method' => $i % 2 === 0 ? 'POST' : 'GET',
                        'path' => '/manage/demo/'.$action,
                    ],
                ]),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === 500) {
                DB::table('audit_logs')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('audit_logs')->insert($rows);
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, User>  $members
     * @param  Collection<int, User>  $staff
     */
    private function presentationEnsureNotifications(Library $library, Collection $books, Collection $members, Collection $staff): void
    {
        $current = UserNotification::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('metadata->presentation_seed', true)
            ->count();
        $missing = max(0, self::PRESENTATION_TARGET_NOTIFICATIONS - $current);
        $types = array_keys(NotificationUiConfig::all());
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $type = $types[$i % count($types)];
            $ui = NotificationUiConfig::for($type);
            $book = $books[($i - 1) % $books->count()];
            $createdAt = $this->presentationSafeTimestamp(now()->subMinutes(($i * 23) % 700000));

            $rows[] = [
                'user_id' => $members[($i - 1) % $members->count()]->id,
                'sent_by' => $staff[$i % $staff->count()]->id,
                'type' => $type,
                'title' => $this->presentationNotificationTitle($type),
                'message' => $this->presentationNotificationMessage($type, $book),
                'related_type' => Book::class,
                'related_id' => $book->id,
                'metadata' => json_encode([
                    'presentation_seed' => true,
                    'library_id' => $library->id,
                    'kind' => $type,
                    'category' => $ui['category'],
                    'category_key' => $ui['category_key'],
                    'color' => $ui['color'],
                    'icon' => $ui['icon'],
                    'book_title' => $book->title,
                ]),
                'read_at' => $i % 4 === 0 ? $createdAt->copy()->addMinutes(15) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === 500) {
                DB::table('user_notifications')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('user_notifications')->insert($rows);
        }
    }

    private function presentationPrintReport(Library $library): void
    {
        $memberIds = LibraryMembership::query()->where('library_id', $library->id)->pluck('user_id');

        $report = [
            'Biblioteka' => $library->name.' (#'.$library->id.')',
            'Filialai' => Branch::query()->where('library_id', $library->id)->count(),
            'Vietos' => Location::query()->where('library_id', $library->id)->count(),
            'Kategorijos' => Category::query()->count(),
            'Leidyklos' => Publisher::query()->count(),
            'Autoriai' => Author::query()->count(),
            'Knygos' => Book::query()->count(),
            'Kopijos' => BookCopy::query()->where('library_id', $library->id)->count(),
            'Nariai' => User::query()->whereIn('id', $memberIds)->where('role', User::ROLE_MEMBER)->count(),
            'Darbuotojai' => User::query()->whereIn('id', $memberIds)->whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])->count(),
            'Paskolos' => Loan::query()->where('library_id', $library->id)->count(),
            'Aktyvios paskolos' => Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_ACTIVE)->whereNull('returned_at')->count(),
            'Veluojancios paskolos' => Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_OVERDUE)->whereNull('returned_at')->count(),
            'Rezervacijos' => Reservation::query()->where('library_id', $library->id)->count(),
            'Scan log' => ScanLog::query()->where('library_id', $library->id)->count(),
            'Audit log' => AuditLog::query()->where('library_id', $library->id)->count(),
            'Pranesimai' => UserNotification::query()->whereIn('user_id', $memberIds)->count(),
        ];

        $this->command?->info('Presentation demo data report:');

        foreach ($report as $label => $value) {
            $this->command?->line($label.': '.$value);
        }
    }

    private function presentationCopyStatusForIndex(int $i): string
    {
        return match (true) {
            $i % 50 === 0 => BookCopy::STATUS_LOST,
            $i % 33 === 0 => BookCopy::STATUS_MAINTENANCE,
            $i % 25 === 0 => BookCopy::STATUS_AVAILABLE,
            $i % 20 === 0 => BookCopy::STATUS_WITHDRAWN,
            $i % 7 === 0 => BookCopy::STATUS_LOANED,
            default => BookCopy::STATUS_AVAILABLE,
        };
    }

    private function presentationReservationStatusForIndex(int $i): string
    {
        return match ($i % 10) {
            0, 1, 2, 3 => Reservation::STATUS_WAITING,
            4, 5 => Reservation::STATUS_FULFILLED,
            6, 7 => Reservation::STATUS_CANCELLED,
            default => Reservation::STATUS_EXPIRED,
        };
    }

    private function presentationCopyNotes(string $status): ?string
    {
        return match ($status) {
            BookCopy::STATUS_AVAILABLE => 'Kopija prieinama greitam isdavimui.',
            BookCopy::STATUS_LOANED => 'Kopija siuo metu naudojama skaitytojo.',
            BookCopy::STATUS_LOST => 'Pazymeta kaip prarasta inventorizacijos metu.',
            BookCopy::STATUS_MAINTENANCE => 'Tvarkoma arba paruosiama grizimui i fonda.',
            BookCopy::STATUS_WITHDRAWN => 'Nenaudojama aktyviame fonde.',
            default => null,
        };
    }

    private function presentationBookDescription(string $category, string $title): string
    {
        return sprintf(
            '%s yra %s srities leidinys, tinkamas tiek kasdieniam skaitymui, tiek mokymuisi. Aprayme aptariamos praktins situacijos, istorinis kontekstas ir temos, kurios padeda bibliotekos lankytojams greitai atsirinkti aktual turin.',
            $title,
            mb_strtolower($category)
        );
    }

    /**
     * @param  class-string  $modelClass
     */
    private function presentationUniqueSlug(string $modelClass, string $value, string $fallback): string
    {
        $base = GeneratesSlugs::from($value, $fallback);
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, BookCopy>  $copies
     * @param  Collection<int, User>  $members
     * @return array{0: class-string, 1: int, 2: string}
     */
    private function presentationAuditTarget(string $action, Collection $books, Collection $copies, Collection $members, int $i): array
    {
        if (str_starts_with($action, 'book_copy')) {
            $copy = $copies[$i % $copies->count()];

            return [BookCopy::class, $copy->id, $copy->inventory_code];
        }

        if (str_starts_with($action, 'user') || str_contains($action, 'staff')) {
            $member = $members[$i % $members->count()];

            return [User::class, $member->id, $member->name];
        }

        $book = $books[$i % $books->count()];

        return [Book::class, $book->id, $book->title];
    }

    private function presentationAuditDescription(string $action, string $label): string
    {
        return match ($action) {
            'loan_issued' => 'Isduota knyga: '.$label,
            'loan_returned' => 'Grazinta knyga: '.$label,
            'reservation_created' => 'Sukurta rezervacija: '.$label,
            'reservation_cancelled' => 'Atsaukta rezervacija: '.$label,
            'book_updated' => 'Atnaujinta knygos informacija: '.$label,
            'book_copy_status_changed' => 'Pakeistas kopijos statusas: '.$label,
            'user_updated' => 'Atnaujintas skaitytojo profilis: '.$label,
            'library_staff_assigned' => 'Darbuotojas priskirtas bibliotekos veiksmui: '.$label,
            default => 'Demonstracinis veiksmas: '.$label,
        };
    }

    private function presentationNotificationTitle(string $type): string
    {
        return NotificationType::normalize($type)->defaultTitle();
    }

    private function presentationNotificationMessage(string $type, Book $book): string
    {
        return match ($type) {
            'reservation_created' => 'Jūsų rezervacija knygai "'.$book->title.'" sukurta.',
            'reservation_ready' => 'Knyga "'.$book->title.'" paruošta atsiėmimui.',
            'reservation_cancelled' => 'Rezervacija knygai "'.$book->title.'" buvo atsaukta.',
            'reservation_expired' => 'Rezervacijos knygai "'.$book->title.'" atsiemimo terminas baigesi.',
            'loan_overdue' => 'Knygos "'.$book->title.'" grazinimo terminas jau praejo.',
            'book_due_soon' => 'Knyga "'.$book->title.'" turetu buti grazinta artimiausiomis dienomis.',
            'book_returned' => 'Knyga "'.$book->title.'" sekmingai grazinta.',
            default => 'Biblioteka atnaujino informacija apie knyga "'.$book->title.'".',
        };
    }

    private function presentationSafeTimestamp(CarbonInterface $timestamp): CarbonInterface
    {
        $safe = $timestamp->copy();

        if ((int) $safe->format('H') === 3) {
            return $safe->setTime(4, 0);
        }

        return $safe;
    }
}
