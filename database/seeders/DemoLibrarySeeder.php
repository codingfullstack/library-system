<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\AuditLog;
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
use App\Support\UserManagement;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLibrarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->clearDemoUsers();
            $this->clearDemoLibraries();
            $this->clearRetiredDemoBooks();
            $this->clearDemoSuperAdmin();

            $libraryX = Library::create([
                'name' => 'Vilniaus miesto centrine biblioteka',
                'code' => 'LIB-X',
                'email' => 'centras@library.test',
                'phone' => '+37060000001',
                'address' => 'Gedimino pr. 12',
                'city' => 'Vilnius',
                'is_active' => true,
            ]);

            $libraryY = Library::create([
                'name' => 'Kauno rajono viesoji biblioteka',
                'code' => 'LIB-Y',
                'email' => 'kaunas@library.test',
                'phone' => '+37060000002',
                'address' => 'Laisves al. 48',
                'city' => 'Kaunas',
                'is_active' => true,
            ]);

            User::create([
                'name' => 'Superadministratorius',
                'email' => 'superadmin@test.com',
                'password' => Hash::make('password'),
                'role' => 'superadministratorius',
                'phone' => '+37060000000',
                'membership_number' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            [$adminX, $staffX, $membersX] = $this->seedLibraryUsers($libraryX, [
                'administratorius' => ['name' => 'Rasa Klimienė', 'email' => 'adminx@test.com', 'phone' => '+37061110001'],
                'darbuotojas' => ['name' => 'Paulius Mockus', 'email' => 'staffx@test.com', 'phone' => '+37062220001'],
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
            ]);

            [$adminY, $staffY, $membersY] = $this->seedLibraryUsers($libraryY, [
                'administratorius' => ['name' => 'Dalia Varnienė', 'email' => 'adminy@test.com', 'phone' => '+37061110002'],
                'darbuotojas' => ['name' => 'Mantas Jasiūnas', 'email' => 'staffy@test.com', 'phone' => '+37062220002'],
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
            ]);

            $categories = $this->seedCategories()->keyBy('slug');
            $publishers = $this->seedPublishers()->keyBy('name');
            $authors = $this->seedAuthors()->keyBy('name');
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

            $employeesX = collect([$adminX, $staffX]);
            $employeesY = collect([$adminY, $staffY]);

            $copiesX = $this->seedCopiesForLibrary($libraryX, $books->shuffle()->take(min(20, $books->count())), $branchesX, $locationsX, 'X', $employeesX, $membersX);
            $copiesY = $this->seedCopiesForLibrary($libraryY, $books->shuffle()->take(min(20, $books->count())), $branchesY, $locationsY, 'Y', $employeesY, $membersY);

            $this->seedReservationsForLibrary($libraryX, $copiesX, $membersX);
            $this->seedReservationsForLibrary($libraryY, $copiesY, $membersY);

            $this->seedScanLogs($libraryX, $copiesX, $employeesX);
            $this->seedScanLogs($libraryY, $copiesY, $employeesY);

            $this->seedAuditLogsForLibrary($libraryX, $books, $copiesX, $employeesX, $membersX);
            $this->seedAuditLogsForLibrary($libraryY, $books, $copiesY, $employeesY, $membersY);
        });
    }

    private function clearDemoLibraries(): void
    {
        Library::query()
            ->whereIn('code', ['LIB-X', 'LIB-Y'])
            ->get()
            ->each(function (Library $library) {
                $library->delete();
            });
    }

    private function clearDemoUsers(): void
    {
        $demoEmails = [
            'adminx@test.com',
            'staffx@test.com',
            'adminy@test.com',
            'staffy@test.com',
            'austeja.kazlauskaite@example.com',
            'mantas.balsevicius@example.com',
            'egle.petrauskaite@example.com',
            'lukas.vaitiekunas@example.com',
            'saule.grigaityte@example.com',
            'rokas.jankauskas@example.com',
            'gabija.rimkute@example.com',
            'emilija.varnyte@example.com',
            'nojus.pocius@example.com',
            'milda.janusauskaite@example.com',
            'tadas.veverskis@example.com',
            'karolina.butkeviciute@example.com',
            'simona.petratyte@example.com',
            'giedre.valentiene@example.com',
            'tomas.vaiktus@example.com',
            'aiste.jakaite@example.com',
            'urte.zukaite@example.com',
            'dovile.kairiene@example.com',
            'milda.gerdvilaite@example.com',
            'povilas.morkunas@example.com',
            'ieva.noreikaite@example.com',
            'domas.vasiliauskas@example.com',
            'goda.lukoceviciute@example.com',
            'ugnius.narbutas@example.com',
            'vakare.simonaityte@example.com',
            'jonas.petraitis@example.com',
            'aiste.maciulyte@example.com',
            'pijus.zabiela@example.com',
            'greta.simkute@example.com',
            'nedas.petrauskas@example.com',
            'paulina.stankute@example.com',
            'rugile.plioplyte@example.com',
            'lina.bertaityte@example.com',
            'viltaras.kvedaras@example.com',
            'monika.vaiciulyte@example.com',
            'elze.mockute@example.com',
            'liepa.rimiene@example.com',
            'darius.venslovas@example.com',
            'neringa.kuodyte@example.com',
            'marius.giedraitis@example.com',
        ];

        User::query()->whereIn('email', $demoEmails)->delete();
    }

    private function clearDemoSuperAdmin(): void
    {
        User::query()->where('email', 'superadmin@test.com')->delete();
    }

    private function clearRetiredDemoBooks(): void
    {
        Book::query()
            ->whereIn('isbn', [
                '9786090135003',
                '9786090142324',
                '9786090142331',
                '9786090143901',
                '9786090145110',
                '9786090145455',
                '9786094273126',
                '9785415011237',
                '9786094661550',
                '9786094793664',
                '9786090151904',
                '9786090155605',
                '9786094272709',
                '9786090155339',
                '9786094665800',
                '9786094272211',
                '9786090139889',
                '9786090158996',
                '9786098142379',
                '9786094797631',
                '9786090145100',
                '9786094270538',
            ])
            ->delete();
    }

    /**
     * @param array{
     *   admin: array{name: string, email: string, phone: string},
     *   staff: array{name: string, email: string, phone: string},
     *   members: list<array{name: string, email: string, phone: string}>
     * } $profiles
     * @return array{0: User, 1: User, 2: Collection<int, User>}
     */
    private function seedLibraryUsers(Library $library, array $profiles): array
    {
        $admin = User::create([
            'name' => $profiles['administratorius']['name'],
            'email' => $profiles['administratorius']['email'],
            'password' => Hash::make('password'),
            'role' => 'administratorius',
            'phone' => $profiles['administratorius']['phone'],
            'membership_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->attachLibraryMembership($admin, $library);

        $staff = User::create([
            'name' => $profiles['darbuotojas']['name'],
            'email' => $profiles['darbuotojas']['email'],
            'password' => Hash::make('password'),
            'role' => 'darbuotojas',
            'phone' => $profiles['darbuotojas']['phone'],
            'membership_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->attachLibraryMembership($staff, $library);

        $members = collect($profiles['members'])
            ->values()
            ->map(function (array $member, int $index) use ($library) {
                $user = User::create([
                    'name' => $member['name'],
                    'email' => $member['email'],
                    'password' => Hash::make('password'),
                    'role' => 'narys',
                    'phone' => $member['phone'],
                    'membership_number' => UserManagement::generateMembershipNumber(),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $this->attachLibraryMembership($user, $library);

                return $user;
            });

        return [$admin, $staff, $members];
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
            return Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $name . ' skiltis bendram katalogui.',
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
            return Author::query()->firstOrCreate(
                ['slug' => Str::slug($author['name'])],
                [
                    'name' => $author['name'],
                    'bio' => $author['bio'],
                ]
            );
        })->values();
    }

    /**
     * @param Collection<string, Category> $categories
     * @param Collection<string, Publisher> $publishers
     * @param Collection<string, Author> $authors
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
            $categoryIds = collect($book['categories'])->map(fn (string $name) => $categories[Str::slug($name)]->id);

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
                collect($book['authors'])->map(fn (string $name) => $authors[$name]->id)->all()
            );

            $record->categories()->sync($categoryIds->all());

            return $record;
        })->values();
    }

    /**
     * @param list<string> $branchNames
     * @return array{0: Collection<int, Branch>, 1: Collection<int, Location>}
     */
    private function seedBranchesAndLocations(Library $library, array $branchNames): array
    {
        $branches = collect($branchNames)->map(function (string $branchName, int $index) use ($library) {
            return Branch::create([
                'library_id' => $library->id,
                'name' => $branchName,
                'code' => $library->code . '-BR-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'address' => $library->address,
                'city' => $library->city,
            ]);
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
                    return Location::create([
                        'library_id' => $library->id,
                        'branch_id' => $branch->id,
                        'name' => $location['name'],
                        'code' => $branch->code . '-LOC-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'room' => $location['room'],
                        'shelf' => $location['shelf'],
                    ]);
                })
            );
        }

        return [$branches->values(), $locations->values()];
    }

    /**
     * @param Collection<int, Book> $books
     * @param Collection<int, Branch> $branches
     * @param Collection<int, Location> $locations
     * @param Collection<int, User> $employees
     * @param Collection<int, User> $members
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
        $copies = collect();
        $inventoryCounter = 1;

        foreach ($books as $book) {
            $count = rand(3, 6);

            for ($i = 0; $i < $count; $i++) {
                $branch = $branches->random();
                $location = $locations->where('branch_id', $branch->id)->random();
                $targetStatus = collect([
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_AVAILABLE,
                    BookCopy::STATUS_LOANED,
                    BookCopy::STATUS_LOANED,
                    BookCopy::STATUS_MAINTENANCE,
                    BookCopy::STATUS_DAMAGED,
                    BookCopy::STATUS_LOST,
                    BookCopy::STATUS_WITHDRAWN,
                ])->random();

                $condition = match ($targetStatus) {
                    BookCopy::STATUS_DAMAGED => 'sugadinta',
                    BookCopy::STATUS_MAINTENANCE => collect(['padėvėta', 'sugadinta'])->random(),
                    BookCopy::STATUS_LOST => collect(['gera', 'padėvėta'])->random(),
                    default => collect(['nauja', 'gera', 'gera', 'padėvėta'])->random(),
                };

                $copy = BookCopy::create([
                    'library_id' => $library->id,
                    'book_id' => $book->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'inventory_code' => sprintf('%s-%s-%03d', $library->code, $prefix, $inventoryCounter++),
                    'qr_code' => sprintf('QR-%s-%04d', $library->code, $inventoryCounter + 500),
                    'barcode' => '978' . str_pad((string) rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT),
                    'status' => BookCopy::STATUS_AVAILABLE,
                    'condition_status' => $condition,
                    'acquired_at' => now()->subMonths(rand(6, 48))->format('Y-m-d'),
                    'notes' => $this->copyNotesForStatus($targetStatus),
                ]);

                $copies->push($copy);
                $this->recordCopyHistory($copy, $employees->first(), 'created', BookCopy::STATUS_AVAILABLE, 'Egzempliorius įtrauktas į bibliotekos fondą.');

                if ($targetStatus === BookCopy::STATUS_LOANED) {
                    $this->seedLoanForCopy($copy, $members->random(), $employees->random(), false);
                    continue;
                }

                if ($targetStatus === BookCopy::STATUS_AVAILABLE) {
                    continue;
                }

                $copy->update(['status' => $targetStatus]);

                [$reasonCode, $notes] = match ($targetStatus) {
                    BookCopy::STATUS_MAINTENANCE => ['sent_to_maintenance', 'Egzempliorius laikinai perduotas tvarkymui.'],
                    BookCopy::STATUS_DAMAGED => ['marked_damaged', 'Apžiūros metu nustatyti fiziniai pažeidimai.'],
                    BookCopy::STATUS_LOST => ['marked_lost', 'Egzempliorius nerastas po inventorizacijos.'],
                    BookCopy::STATUS_WITHDRAWN => ['nurašyta', 'Egzempliorius nurašytas dėl nusidėvėjimo.'],
                    default => ['status_adjusted', 'Statusas atnaujintas demo duomenims.'],
                };

                $this->recordCopyHistory($copy, $employees->random(), $reasonCode, $targetStatus, $notes);
            }
        }

        $availableCopies = $copies->filter(fn (BookCopy $copy) => $copy->status === BookCopy::STATUS_AVAILABLE)->values();

        foreach ($availableCopies->shuffle()->take(min(20, $availableCopies->count())) as $copy) {
            $this->seedLoanForCopy($copy, $members->random(), $employees->random(), true);
        }

        return $copies->values();
    }

    private function seedLoanForCopy(BookCopy $copy, User $member, User $employee, bool $returned): void
    {
        $borrowedAt = $this->safeTimestamp(now()->subDays(rand(3, 40))->subHours(rand(1, 8)));
        $dueAt = $this->safeTimestamp((clone $borrowedAt)->addDays(14));
        $returnedAt = $returned ? $this->safeTimestamp((clone $borrowedAt)->addDays(rand(4, 16))) : null;
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
            'renewal_count' => $returned ? rand(0, 1) : rand(0, 2),
            'notes' => $returned
                ? 'Demonstracinis grąžinimo įrašas.'
                : 'Skaitytojas šiuo metu naudojasi šia kopija.',
        ]);

        $copy->update(['status' => BookCopy::STATUS_LOANED]);
        $this->recordCopyHistory($copy, $employee, 'issued', BookCopy::STATUS_LOANED, 'Egzempliorius išduotas skaitytojui.');

        if ($returned) {
            $copy->update(['status' => BookCopy::STATUS_AVAILABLE]);
            $this->recordCopyHistory($copy, $employee, 'grąžinta', BookCopy::STATUS_AVAILABLE, 'Egzempliorius grąžintas laiku ir vėl prieinamas fonde.');
        }
    }

    /**
     * @param Collection<int, BookCopy> $copies
     * @param Collection<int, User> $members
     */
    private function seedReservationsForLibrary(Library $library, Collection $copies, Collection $members): void
    {
        $books = $copies->pluck('book')->filter()->unique('id')->values();

        foreach ($books->shuffle()->take(min(16, $books->count())) as $book) {
            $queuedMembers = $members->shuffle()->take(rand(2, 4))->values();
            $reservedAt = $this->safeTimestamp(now()->subDays(rand(1, 10))->subHours(rand(1, 10)));

            foreach ($queuedMembers as $position => $member) {
                $this->seedReservationForBook(
                    $library,
                    $book,
                    $member,
                    Reservation::STATUS_RESERVED,
                    (clone $reservedAt)->addMinutes($position * 20),
                    now()->addDays(rand(2, 6)),
                    'Narys laukia, kol atsiras laisva šios knygos kopija.'
                );
            }

            foreach ($members->whereNotIn('id', $queuedMembers->pluck('id'))->shuffle()->take(rand(2, 4)) as $historicalMember) {
                $historicalStatus = collect([
                    Reservation::STATUS_FULFILLED,
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_EXPIRED,
                ])->random();

                $historicalReservedAt = $this->safeTimestamp(now()->subDays(rand(12, 25))->subHours(rand(1, 12)));

                $this->seedReservationForBook(
                    $library,
                    $book,
                    $historicalMember,
                    $historicalStatus,
                    $historicalReservedAt,
                    $historicalStatus === Reservation::STATUS_EXPIRED ? now()->subDays(rand(1, 4)) : null,
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
            'reserved_at' => $reservedAt,
            'expires_at' => in_array($status, [Reservation::STATUS_RESERVED, Reservation::STATUS_EXPIRED], true)
                ? ($expiresAt ?? now()->addDays(4))
                : null,
            'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? now()->subDays(rand(1, 5)) : null,
            'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? now()->subDays(rand(1, 4)) : null,
            'notes' => $notes,
        ]);
    }

    /**
     * @param Collection<int, BookCopy> $copies
     * @param Collection<int, User> $employees
     */
    private function seedScanLogs(Library $library, Collection $copies, Collection $employees): void
    {
        foreach ($copies->shuffle()->take(min(40, $copies->count())) as $copy) {
            ScanLog::create([
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $employees->random()->id,
                'scan_value' => $copy->qr_code,
                'scan_type' => collect(['info', 'loan', 'return', 'inventory'])->random(),
                'result' => collect(['success', 'success', 'success', 'error'])->random(),
                'device_info' => collect(['Samsung A54', 'Samsung Tab A9', 'Web scanner', 'Chrome Windows'])->random(),
            ]);
        }
    }

    /**
     * @param Collection<int, Book> $books
     * @param Collection<int, BookCopy> $copies
     * @param Collection<int, User> $employees
     * @param Collection<int, User> $members
     */
    private function seedAuditLogsForLibrary(
        Library $library,
        Collection $books,
        Collection $copies,
        Collection $employees,
        Collection $members
    ): void {
        $bookSamples = $books->take(3)->values();

        foreach ($bookSamples as $bookIndex => $book) {
            foreach (range(1, 12) as $step) {
                $actor = $employees->random();
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
                        'target_member_id' => $members->random()->id,
                    ],
                ], $createdAt);
            }
        }

        foreach ($copies->take(5)->values() as $copyIndex => $copy) {
            foreach (range(1, 10) as $step) {
                $actor = $employees->random();
                $createdAt = $this->safeTimestamp(now()->subDays(18 - $copyIndex)->addMinutes($step * 35));

                $this->createAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 2 === 0 ? 'book_copy_status_changed' : 'book_copy_updated',
                    'auditable_type' => $copy->getMorphClass(),
                    'auditable_id' => $copy->id,
                    'description' => $step % 2 === 0
                        ? sprintf('Egzemplioriaus %s statusas pakeistas.', $copy->inventory_code)
                        : sprintf('Atnaujinta egzemplioriaus %s informacija.', $copy->inventory_code),
                    'metadata' => [
                        'inventory_code' => $copy->inventory_code,
                        'target_status_label' => \App\Models\BookCopy::statusLabels()[$copy->status] ?? $copy->status,
                    ],
                ], $createdAt);
            }
        }

        foreach ($members->take(4)->values() as $memberIndex => $member) {
            foreach (range(1, 6) as $step) {
                $actor = $employees->random();
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
            BookCopy::STATUS_AVAILABLE => collect([
                null,
                'Kopija tvarkinga ir prieinama skaitytojams.',
                'Pastaruoju metu dažnai ieškoma prie informacijos stalo.',
            ])->random(),
            BookCopy::STATUS_LOANED => 'Kopija šiuo metu išduota skaitytojui.',
            BookCopy::STATUS_MAINTENANCE => 'Laukiama smulkaus taisymo arba perklijavimo.',
            BookCopy::STATUS_DAMAGED => 'Matomi susidėvėjimo požymiai, reikia įvertinti būklę.',
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
            ? Carbon::parse($lastChangedAt)->addHours(rand(6, 48))
            : ($copy->acquired_at
                ? Carbon::parse($copy->acquired_at)->startOfDay()->addDays(rand(1, 20))->addHours(rand(8, 17))
                : now()->subMonths(6)->addDays(rand(1, 15))->addHours(rand(8, 17)));

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
}




