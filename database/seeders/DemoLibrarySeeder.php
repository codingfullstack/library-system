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
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\ScanLog;
use App\Models\User;
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
                'library_id' => null,
                'name' => 'Super Admin',
                'email' => 'superadmin@test.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'phone' => '+37060000000',
                'membership_number' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            [$adminX, $staffX, $membersX] = $this->seedLibraryUsers($libraryX, [
                'admin' => ['name' => 'Rasa Klimiene', 'email' => 'adminx@test.com', 'phone' => '+37061110001'],
                'staff' => ['name' => 'Paulius Mockus', 'email' => 'staffx@test.com', 'phone' => '+37062220001'],
                'members' => [
                    ['name' => 'Austeja Kazlauskaite', 'email' => 'austeja.kazlauskaite@example.com', 'phone' => '+37061234001'],
                    ['name' => 'Mantas Balsevicius', 'email' => 'mantas.balsevicius@example.com', 'phone' => '+37061234002'],
                    ['name' => 'Egle Petrauskaite', 'email' => 'egle.petrauskaite@example.com', 'phone' => '+37061234003'],
                    ['name' => 'Lukas Vaitiekunas', 'email' => 'lukas.vaitiekunas@example.com', 'phone' => '+37061234004'],
                    ['name' => 'Saule Grigaityte', 'email' => 'saule.grigaityte@example.com', 'phone' => '+37061234005'],
                    ['name' => 'Rokas Jankauskas', 'email' => 'rokas.jankauskas@example.com', 'phone' => '+37061234006'],
                    ['name' => 'Gabija Rimkute', 'email' => 'gabija.rimkute@example.com', 'phone' => '+37061234007'],
                    ['name' => 'Emilija Varnyte', 'email' => 'emilija.varnyte@example.com', 'phone' => '+37061234008'],
                    ['name' => 'Nojus Pocius', 'email' => 'nojus.pocius@example.com', 'phone' => '+37061234009'],
                    ['name' => 'Milda Janusauskaite', 'email' => 'milda.janusauskaite@example.com', 'phone' => '+37061234010'],
                    ['name' => 'Tadas Veverskis', 'email' => 'tadas.veverskis@example.com', 'phone' => '+37061234011'],
                    ['name' => 'Karolina Butkeviciute', 'email' => 'karolina.butkeviciute@example.com', 'phone' => '+37061234012'],
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
                'admin' => ['name' => 'Dalia Varniene', 'email' => 'adminy@test.com', 'phone' => '+37061110002'],
                'staff' => ['name' => 'Mantas Jasiunas', 'email' => 'staffy@test.com', 'phone' => '+37062220002'],
                'members' => [
                    ['name' => 'Ieva Noreikaite', 'email' => 'ieva.noreikaite@example.com', 'phone' => '+37061235001'],
                    ['name' => 'Domas Vasiliauskas', 'email' => 'domas.vasiliauskas@example.com', 'phone' => '+37061235002'],
                    ['name' => 'Goda Lukoceviciute', 'email' => 'goda.lukoceviciute@example.com', 'phone' => '+37061235003'],
                    ['name' => 'Ugnius Narbutas', 'email' => 'ugnius.narbutas@example.com', 'phone' => '+37061235004'],
                    ['name' => 'Vakare Simonaityte', 'email' => 'vakare.simonaityte@example.com', 'phone' => '+37061235005'],
                    ['name' => 'Jonas Petraitis', 'email' => 'jonas.petraitis@example.com', 'phone' => '+37061235006'],
                    ['name' => 'Aiste Maciulyte', 'email' => 'aiste.maciulyte@example.com', 'phone' => '+37061235007'],
                    ['name' => 'Pijus Zabiela', 'email' => 'pijus.zabiela@example.com', 'phone' => '+37061235008'],
                    ['name' => 'Greta Simkute', 'email' => 'greta.simkute@example.com', 'phone' => '+37061235009'],
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

            $categories = $this->seedCategories()->keyBy('name');
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
                'Kalnieciai',
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
            'library_id' => $library->id,
            'name' => $profiles['admin']['name'],
            'email' => $profiles['admin']['email'],
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => $profiles['admin']['phone'],
            'membership_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'library_id' => $library->id,
            'name' => $profiles['staff']['name'],
            'email' => $profiles['staff']['email'],
            'password' => Hash::make('password'),
            'role' => 'staff',
            'phone' => $profiles['staff']['phone'],
            'membership_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $members = collect($profiles['members'])
            ->values()
            ->map(function (array $member, int $index) use ($library) {
                return User::create([
                    'library_id' => $library->id,
                    'name' => $member['name'],
                    'email' => $member['email'],
                    'password' => Hash::make('password'),
                    'role' => 'member',
                    'phone' => $member['phone'],
                    'membership_number' => sprintf('%s-MEM-%03d', $library->code, $index + 1),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            });

        return [$admin, $staff, $members];
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
            ['name' => 'J. R. R. Tolkien', 'bio' => 'Britu rasytojas, sukures Vidurzemes pasauli.'],
            ['name' => 'George Orwell', 'bio' => 'Anglu rasytojas ir eseistas, zinomas del distopiniu romanu.'],
            ['name' => 'Antoine de Saint-Exupery', 'bio' => 'Prancuzu rasytojas ir lakunas, parases Mazaji princa.'],
            ['name' => 'James Clear', 'bio' => 'Autorius, tyrinejantis iprociu formavima ir kasdienius pokycius.'],
            ['name' => 'Daniel Kahneman', 'bio' => 'Psichologas ir Nobelio premijos laureatas, tyrinejes sprendimu priemima.'],
            ['name' => 'Yuval Noah Harari', 'bio' => 'Izraelio istorikas, rasantis apie civilizacijos raida.'],
            ['name' => 'Vincas Mykolaitis-Putinas', 'bio' => 'Lietuviu rasytojas, poetas ir literaturos istorikas.'],
            ['name' => 'Balys Sruoga', 'bio' => 'Lietuviu rasytojas ir dramaturgas.'],
            ['name' => 'Kristina Sabaliauskaite', 'bio' => 'Lietuviu rasytoja, istorinio romano zanro atstove.'],
            ['name' => 'Aldous Huxley', 'bio' => 'Anglu rasytojas, distopinio romano autorius.'],
            ['name' => 'Delia Owens', 'bio' => 'Amerikieciu rasytoja ir gamtininke.'],
            ['name' => 'Frank Herbert', 'bio' => 'Amerikieciu fantastas, parasess Kopa.'],
            ['name' => 'J. K. Rowling', 'bio' => 'Britu rasytoja, Hario Poterio serijos autore.'],
            ['name' => 'John Green', 'bio' => 'Amerikieciu jaunimo literaturos autorius.'],
            ['name' => 'Andrzej Sapkowski', 'bio' => 'Lenku fantastikos rasytojas, Raganiaus ciklo kurejas.'],
            ['name' => 'Sally Rooney', 'bio' => 'Airiu rasytoja, rasanti apie siuolaikinius santykius.'],
            ['name' => 'Richard Osman', 'bio' => 'Britu autorius, zinomas del lengvu detektyvu.'],
            ['name' => 'Tina Oziewicz', 'bio' => 'Lenku autore, kurianti vaiku literaturos knygas.'],
            ['name' => 'Marius Marcinkevicius', 'bio' => 'Lietuviu autorius, rasantis vaikams ir jaunimui.'],
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
                'title' => 'Hobitas',
                'isbn' => '9786090135003',
                'description' => 'Nuotykiu romanas apie Bilba Beginsa ir kelione i Vieniso kalno urvus.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Fantastika',
                'categories' => ['Fantastika', 'Klasika'],
                'authors' => ['J. R. R. Tolkien'],
                'publication_year' => 1937,
                'language' => 'lt',
                'page_count' => 304,
                'edition' => '3',
            ],
            [
                'title' => '1984',
                'isbn' => '9786090142324',
                'description' => 'Distopinis romanas apie visuotine kontrole ir tiesos perrasinejima.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Klasika',
                'categories' => ['Klasika', 'Romanai'],
                'authors' => ['George Orwell'],
                'publication_year' => 1949,
                'language' => 'lt',
                'page_count' => 352,
                'edition' => '2',
            ],
            [
                'title' => 'Gyvuliu ukis',
                'isbn' => '9786090142331',
                'description' => 'Trumpas politinis romanas apie valdzios, propagandos ir laisves tema.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Klasika',
                'categories' => ['Klasika', 'Romanai'],
                'authors' => ['George Orwell'],
                'publication_year' => 1945,
                'language' => 'lt',
                'page_count' => 144,
                'edition' => '2',
            ],
            [
                'title' => 'Mazasis princas',
                'isbn' => '9786090143901',
                'description' => 'Poetine istorija apie draugyste, atsakomybe ir vaizduote.',
                'publisher' => 'Nieko rimto',
                'primary_category' => 'Vaiku literatura',
                'categories' => ['Vaiku literatura', 'Klasika'],
                'authors' => ['Antoine de Saint-Exupery'],
                'publication_year' => 1943,
                'language' => 'lt',
                'page_count' => 128,
                'edition' => '5',
            ],
            [
                'title' => 'Atominiai iprociai',
                'isbn' => '9786090145110',
                'description' => 'Praktiska knyga apie mazu kasdieniu iprociu itaka ilgalaikiams rezultatams.',
                'publisher' => 'Sofoklis',
                'primary_category' => 'Psichologija',
                'categories' => ['Psichologija', 'Verslas'],
                'authors' => ['James Clear'],
                'publication_year' => 2018,
                'language' => 'lt',
                'page_count' => 320,
                'edition' => '1',
            ],
            [
                'title' => 'Mastymas, greitas ir letas',
                'isbn' => '9786090145455',
                'description' => 'Knyga apie du mastymo budus ir ju itaka musu sprendimams.',
                'publisher' => 'Baltos lankos',
                'primary_category' => 'Psichologija',
                'categories' => ['Psichologija', 'Mokslas'],
                'authors' => ['Daniel Kahneman'],
                'publication_year' => 2011,
                'language' => 'lt',
                'page_count' => 512,
                'edition' => '1',
            ],
            [
                'title' => 'Sapiens. Zmonijos trumpa istorija',
                'isbn' => '9786094273126',
                'description' => 'Plati zmonijos istorijos apzvalga nuo pirmuju bendruomeniu iki siu dienu.',
                'publisher' => 'Kitos knygos',
                'primary_category' => 'Istorija',
                'categories' => ['Istorija', 'Mokslas'],
                'authors' => ['Yuval Noah Harari'],
                'publication_year' => 2011,
                'language' => 'lt',
                'page_count' => 432,
                'edition' => '2',
            ],
            [
                'title' => 'Altoriu sesely',
                'isbn' => '9785415011237',
                'description' => 'Vienas svarbiausiu lietuviu literaturos romanu apie pasaukimo ir tapatybes konflikta.',
                'publisher' => 'Vaga',
                'primary_category' => 'Klasika',
                'categories' => ['Klasika', 'Romanai'],
                'authors' => ['Vincas Mykolaitis-Putinas'],
                'publication_year' => 1933,
                'language' => 'lt',
                'page_count' => 600,
                'edition' => '1',
            ],
            [
                'title' => 'Dievu miskas',
                'isbn' => '9786094661550',
                'description' => 'Atsiminimu knyga apie gyvenima koncentracijos stovykloje.',
                'publisher' => 'Vaga',
                'primary_category' => 'Klasika',
                'categories' => ['Klasika', 'Istorija'],
                'authors' => ['Balys Sruoga'],
                'publication_year' => 1957,
                'language' => 'lt',
                'page_count' => 480,
                'edition' => '2',
            ],
            [
                'title' => 'Silva rerum',
                'isbn' => '9786094793664',
                'description' => 'Istorinis romanas apie didiku seimos gyvenima senojoje Lietuvoje.',
                'publisher' => 'Baltos lankos',
                'primary_category' => 'Istorija',
                'categories' => ['Istorija', 'Romanai'],
                'authors' => ['Kristina Sabaliauskaite'],
                'publication_year' => 2008,
                'language' => 'lt',
                'page_count' => 432,
                'edition' => '4',
            ],
            [
                'title' => 'Puikus naujas pasaulis',
                'isbn' => '9786090151904',
                'description' => 'Distopinis romanas apie technologiskai valdoma visuomene.',
                'publisher' => 'Tyto alba',
                'primary_category' => 'Fantastika',
                'categories' => ['Fantastika', 'Klasika'],
                'authors' => ['Aldous Huxley'],
                'publication_year' => 1932,
                'language' => 'lt',
                'page_count' => 288,
                'edition' => '1',
            ],
            [
                'title' => 'Ten, kur gieda veziiai',
                'isbn' => '9786090155605',
                'description' => 'Romanas apie vienatve, gamta ir paslaptinga nusikaltima.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Romanai',
                'categories' => ['Romanai', 'Detektyvai'],
                'authors' => ['Delia Owens'],
                'publication_year' => 2018,
                'language' => 'lt',
                'page_count' => 416,
                'edition' => '2',
            ],
            [
                'title' => 'Kopa',
                'isbn' => '9786094272709',
                'description' => 'Monumentalus fantastinis romanas apie galia, religija ir islikima dykumos planetoje.',
                'publisher' => 'Eridanas',
                'primary_category' => 'Fantastika',
                'categories' => ['Fantastika', 'Mokslas'],
                'authors' => ['Frank Herbert'],
                'publication_year' => 1965,
                'language' => 'lt',
                'page_count' => 560,
                'edition' => '2',
            ],
            [
                'title' => 'Haris Poteris ir Isminties akmuo',
                'isbn' => '9786090155339',
                'description' => 'Pirmoji serijos dalis apie jauna burtininka Hari Poteri.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Fantastika',
                'categories' => ['Fantastika', 'Jaunimo literatura'],
                'authors' => ['J. K. Rowling'],
                'publication_year' => 1997,
                'language' => 'lt',
                'page_count' => 352,
                'edition' => '4',
            ],
            [
                'title' => 'Del musu likimo ir zvaigzdziu kaltos',
                'isbn' => '9786094665800',
                'description' => 'Jaunimo romanas apie draugyste, meile ir netektis.',
                'publisher' => 'Tyto alba',
                'primary_category' => 'Jaunimo literatura',
                'categories' => ['Jaunimo literatura', 'Romanai'],
                'authors' => ['John Green'],
                'publication_year' => 2012,
                'language' => 'lt',
                'page_count' => 288,
                'edition' => '3',
            ],
            [
                'title' => 'Raganius. Paskutinis noras',
                'isbn' => '9786094272211',
                'description' => 'Apsakymu rinkinys apie Geralta is Rivijos.',
                'publisher' => 'Eridanas',
                'primary_category' => 'Fantastika',
                'categories' => ['Fantastika', 'Jaunimo literatura'],
                'authors' => ['Andrzej Sapkowski'],
                'publication_year' => 1993,
                'language' => 'lt',
                'page_count' => 320,
                'edition' => '2',
            ],
            [
                'title' => 'Normalus zmones',
                'isbn' => '9786090139889',
                'description' => 'Siuolaikinis romanas apie artuma, klase ir saviverte.',
                'publisher' => 'Alma littera',
                'primary_category' => 'Romanai',
                'categories' => ['Romanai'],
                'authors' => ['Sally Rooney'],
                'publication_year' => 2018,
                'language' => 'lt',
                'page_count' => 272,
                'edition' => '1',
            ],
            [
                'title' => 'Ketvirtadienio zmogzudysciu klubas',
                'isbn' => '9786090158996',
                'description' => 'Lengvas detektyvas apie senjoru kluba, tirianti nusikaltimus.',
                'publisher' => 'Tyto alba',
                'primary_category' => 'Detektyvai',
                'categories' => ['Detektyvai', 'Romanai'],
                'authors' => ['Richard Osman'],
                'publication_year' => 2020,
                'language' => 'lt',
                'page_count' => 384,
                'edition' => '1',
            ],
            [
                'title' => 'Jausmai',
                'isbn' => '9786098142379',
                'description' => 'Vaikams skirta knyga apie tai, kaip pazinti ir ivardyti jausmus.',
                'publisher' => 'Nieko rimto',
                'primary_category' => 'Vaiku literatura',
                'categories' => ['Vaiku literatura', 'Psichologija'],
                'authors' => ['Tina Oziewicz'],
                'publication_year' => 2019,
                'language' => 'lt',
                'page_count' => 72,
                'edition' => '1',
            ],
            [
                'title' => 'Akmenelis',
                'isbn' => '9786094797631',
                'description' => 'Vaikams ir paaugliams skirta istorija apie drasa ir bendryste.',
                'publisher' => 'Baltos lankos',
                'primary_category' => 'Vaiku literatura',
                'categories' => ['Vaiku literatura', 'Jaunimo literatura'],
                'authors' => ['Marius Marcinkevicius'],
                'publication_year' => 2021,
                'language' => 'lt',
                'page_count' => 104,
                'edition' => '1',
            ],
        ];

        return collect($catalog)->map(function (array $book) use ($categories, $publishers, $authors) {
            $record = Book::query()->firstOrCreate(
                ['isbn' => $book['isbn']],
                [
                    'title' => $book['title'],
                    'subtitle' => null,
                    'isbn' => $book['isbn'],
                    'description' => $book['description'],
                    'publisher_id' => $publishers[$book['publisher']]->id,
                    'category_id' => $categories[$book['primary_category']]->id,
                    'publication_year' => $book['publication_year'],
                    'language' => $book['language'],
                    'page_count' => $book['page_count'],
                    'edition' => $book['edition'],
                    'cover_image' => null,
                ]
            );

            $record->authors()->syncWithoutDetaching(
                collect($book['authors'])->map(fn (string $name) => $authors[$name]->id)->all()
            );

            $record->categories()->syncWithoutDetaching(
                collect($book['categories'])->map(fn (string $name) => $categories[$name]->id)->all()
            );

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
                    BookCopy::STATUS_DAMAGED => 'damaged',
                    BookCopy::STATUS_MAINTENANCE => collect(['worn', 'damaged'])->random(),
                    BookCopy::STATUS_LOST => collect(['good', 'worn'])->random(),
                    default => collect(['new', 'good', 'good', 'worn'])->random(),
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
                $this->recordCopyHistory($copy, $employees->first(), 'created', BookCopy::STATUS_AVAILABLE, 'Egzempliorius itrauktas i bibliotekos fonda.');

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
                    BookCopy::STATUS_DAMAGED => ['marked_damaged', 'Apziuros metu nustatyti fiziniai pazeidimai.'],
                    BookCopy::STATUS_LOST => ['marked_lost', 'Egzempliorius nerastas po inventorizacijos.'],
                    BookCopy::STATUS_WITHDRAWN => ['withdrawn', 'Egzempliorius nurasytas del nusidevejimo.'],
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
        $borrowedAt = now()->subDays(rand(3, 40))->subHours(rand(1, 8));
        $dueAt = (clone $borrowedAt)->addDays(14);
        $returnedAt = $returned ? (clone $borrowedAt)->addDays(rand(4, 16)) : null;
        $status = $returned
            ? 'returned'
            : (now()->gt($dueAt) ? 'overdue' : 'active');

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
                ? 'Demonstracinis grazinimo irasas.'
                : 'Skaitytojas siuo metu naudojasi sia kopija.',
        ]);

        $copy->update(['status' => BookCopy::STATUS_LOANED]);
        $this->recordCopyHistory($copy, $employee, 'issued', BookCopy::STATUS_LOANED, 'Egzempliorius isduotas skaitytojui.');

        if ($returned) {
            $copy->update(['status' => BookCopy::STATUS_AVAILABLE]);
            $this->recordCopyHistory($copy, $employee, 'returned', BookCopy::STATUS_AVAILABLE, 'Egzempliorius grazintas laiku ir vel prieinamas fonde.');
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
            $reservedAt = now()->subDays(rand(1, 10))->subHours(rand(1, 10));

            foreach ($queuedMembers as $position => $member) {
                $this->seedReservationForBook(
                    $library,
                    $book,
                    $member,
                    Reservation::STATUS_RESERVED,
                    (clone $reservedAt)->addMinutes($position * 20),
                    now()->addDays(rand(2, 6)),
                    'Narys laukia, kol atsiras laisva sios knygos kopija.'
                );
            }

            foreach ($members->whereNotIn('id', $queuedMembers->pluck('id'))->shuffle()->take(rand(2, 4)) as $historicalMember) {
                $historicalStatus = collect([
                    Reservation::STATUS_FULFILLED,
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_EXPIRED,
                ])->random();

                $historicalReservedAt = now()->subDays(rand(12, 25))->subHours(rand(1, 12));

                $this->seedReservationForBook(
                    $library,
                    $book,
                    $historicalMember,
                    $historicalStatus,
                    $historicalReservedAt,
                    $historicalStatus === Reservation::STATUS_EXPIRED ? now()->subDays(rand(1, 4)) : null,
                    match ($historicalStatus) {
                        Reservation::STATUS_FULFILLED => 'Rezervacija buvo sekmingai ivykdyta ir knyga atsiimta.',
                        Reservation::STATUS_CANCELLED => 'Narys atsauke rezervacija telefonu.',
                        Reservation::STATUS_EXPIRED => 'Narys laiku neatsieme knygos.',
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
                $createdAt = now()->subDays(40 - ($bookIndex * 8))->addHours($step);

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
                $createdAt = now()->subDays(18 - $copyIndex)->addMinutes($step * 35);

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
                $createdAt = now()->subDays(12 - $memberIndex)->addMinutes($step * 50);

                $this->createAuditLog([
                    'user_id' => $actor->id,
                    'library_id' => $library->id,
                    'action' => $step % 2 === 0 ? 'user_updated' : 'loan_issued',
                    'auditable_type' => $member->getMorphClass(),
                    'auditable_id' => $member->id,
                    'description' => $step % 2 === 0
                        ? sprintf('Atnaujintas vartotojas "%s".', $member->name)
                        : sprintf('Knyga isduota vartotojui "%s".', $member->name),
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
                'Pastaruoju metu daznai ieskoma prie informacijos stalo.',
            ])->random(),
            BookCopy::STATUS_LOANED => 'Kopija siuo metu isduota skaitytojui.',
            BookCopy::STATUS_MAINTENANCE => 'Laukiama smulkaus taisymo arba perklijavimo.',
            BookCopy::STATUS_DAMAGED => 'Matomi susidevejimo pozymiai, reikia ivertinti bukle.',
            BookCopy::STATUS_LOST => 'Nepavyko rasti per paskutine inventorizacija.',
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
}
