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
use App\Support\Notifications\NotificationUiConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PresentationDemoDataSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'adminx@test.com';
    private const PREFIX = 'PRES';

    private const TARGET_BRANCHES = 8;
    private const TARGET_LOCATIONS = 120;
    private const TARGET_CATEGORIES = 42;
    private const TARGET_PUBLISHERS = 75;
    private const TARGET_AUTHORS = 300;
    private const TARGET_BOOKS = 1200;
    private const TARGET_COPIES = 3600;
    private const TARGET_MEMBERS = 650;
    private const TARGET_STAFF = 18;
    private const TARGET_LOANS = 3600;
    private const TARGET_ACTIVE_LOANS = 500;
    private const TARGET_OVERDUE_LOANS = 120;
    private const TARGET_RESERVATIONS = 1500;
    private const TARGET_SCAN_LOGS = 5500;
    private const TARGET_AUDIT_LOGS = 11000;
    private const TARGET_NOTIFICATIONS = 5500;

    /**
     * @var array<int, string>
     */
    private array $conditionStatuses = ['nauja', 'gera', 'padÄ—vÄ—ta', 'sugadinta'];

    public function run(): void
    {
        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();

        if (! $admin) {
            $this->command?->error('User adminx@test.com was not found. Run the base demo seed first or create the target administrator.');

            return;
        }

        $library = $admin->library;

        if (! $library) {
            $this->command?->error('User adminx@test.com does not have an active library membership.');

            return;
        }

        $branches = $this->ensureBranches($library);
        $locations = $this->ensureLocations($library, $branches);
        $categories = $this->ensureCategories();
        $publishers = $this->ensurePublishers();
        $authors = $this->ensureAuthors();
        $books = $this->ensureBooks($categories, $publishers, $authors);
        $staff = $this->ensureStaff($library, $branches, $admin);
        $members = $this->ensureMembers($library);
        $copies = $this->ensureCopies($library, $books, $branches, $locations, $staff);

        $this->ensureLoans($library, $copies, $members, $staff);
        $this->ensureReservations($library, $books, $branches, $members);
        $this->ensureScanLogs($library, $copies, $staff);
        $this->ensureAuditLogs($library, $books, $copies, $members, $staff);
        $this->ensureNotifications($library, $books, $members, $staff);

        $this->printReport($library->refresh());
    }

    /**
     * @return Collection<int, Branch>
     */
    private function ensureBranches(Library $library): Collection
    {
        $definitions = [
            ['Centrine biblioteka', 'Gedimino pr. 12', 'Vilnius'],
            ['Vaiku skyrius', 'Traku g. 8', 'Vilnius'],
            ['Jaunimo skyrius', 'Pylimo g. 21', 'Vilnius'],
            ['Technikos skyrius', 'Konstitucijos pr. 14', 'Vilnius'],
            ['Istorijos skyrius', 'Didzioji g. 5', 'Vilnius'],
            ['Meno skyrius', 'Maironio g. 10', 'Vilnius'],
            ['Moksline skaitykla', 'Universiteto g. 3', 'Vilnius'],
            ['Regioninis skyrius', 'Ukmerges g. 120', 'Vilnius'],
        ];

        foreach ($definitions as $index => [$name, $address, $city]) {
            Branch::query()->updateOrCreate(
                ['library_id' => $library->id, 'code' => self::PREFIX.'-B'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                ['name' => $name, 'address' => $address, 'city' => $city]
            );
        }

        return Branch::query()
            ->where('library_id', $library->id)
            ->orderByRaw("code like '".self::PREFIX."-%' desc")
            ->orderBy('id')
            ->take(max(self::TARGET_BRANCHES, Branch::query()->where('library_id', $library->id)->count()))
            ->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, Location>
     */
    private function ensureLocations(Library $library, Collection $branches): Collection
    {
        $names = [
            'Skaitykla A', 'Skaitykla B', 'Lentyna A1', 'Lentyna A2', 'Lentyna B1',
            'Lentyna B2', 'Lentyna C1', 'Archyvas', 'Sandelys', 'Naujos knygos',
            'Retu leidiniu fondas', 'Periodikos zona', 'Vaiku kampas', 'Kompiuteriu sale',
            'Tyrimu stalai',
        ];

        $perBranch = (int) ceil(self::TARGET_LOCATIONS / max(1, $branches->count()));

        foreach ($branches as $branchIndex => $branch) {
            for ($i = 1; $i <= $perBranch; $i++) {
                $code = self::PREFIX.'-L'.str_pad((string) $branch->id, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
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
    private function ensureCategories(): Collection
    {
        $names = [
            'Fantastika', 'Moksline fantastika', 'Romanai', 'Istorija', 'Biografijos',
            'IT', 'Programavimas', 'Duomenu bazes', 'Tinklai', 'Dirbtinis intelektas',
            'Psichologija', 'Medicina', 'Teise', 'Ekonomika', 'Finansai', 'Religija',
            'Menas', 'Muzika', 'Poezija', 'Vaiku literatura', 'Jaunimo literatura',
            'Klasika', 'Detektyvai', 'Trileriai', 'Keliones', 'Verslas', 'Vadyba',
            'Marketingas', 'Pedagogika', 'Filosofija', 'Sociologija', 'Politika',
            'Gamtos mokslai', 'Matematika', 'Fizika', 'Chemija', 'Inzinerija',
            'Architektura', 'Kulinarija', 'Sveikata', 'Sportas', 'Lietuviu literatura',
        ];

        foreach (array_slice($names, 0, self::TARGET_CATEGORIES) as $name) {
            Category::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => $this->uniqueSlug(Category::class, $name, 'kategorija'),
                    'description' => $name.' kategorija demonstraciniam katalogui.',
                ]
            );
        }

        return Category::query()->whereIn('name', array_slice($names, 0, self::TARGET_CATEGORIES))->get();
    }

    /**
     * @return Collection<int, Publisher>
     */
    private function ensurePublishers(): Collection
    {
        $countries = ['Lietuva', 'Latvija', 'Estija', 'Lenkija', 'Vokietija', 'Prancuzija', 'Jungtine Karalyste', 'JAV'];
        $baseNames = [
            'Alma littera', 'Tyto alba', 'Vaga', 'Baltos lankos', 'Sofoklis', 'Kitos knygos',
            'Nieko rimto', 'Penguin Books', 'Random House', 'Springer', 'O Reilly Media',
            'No Starch Press', 'MIT Press', 'Oxford University Press', 'Cambridge Press',
        ];

        for ($i = 1; $i <= self::TARGET_PUBLISHERS; $i++) {
            $name = $baseNames[$i - 1] ?? self::PREFIX.' Leidykla '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            Publisher::query()->firstOrCreate(
                ['name' => $name],
                ['country' => $countries[($i - 1) % count($countries)]]
            );
        }

        return Publisher::query()
            ->where(function ($query): void {
                $query->where('name', 'like', self::PREFIX.' Leidykla %')
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
    private function ensureAuthors(): Collection
    {
        $firstNames = ['Jonas', 'Ieva', 'Mantas', 'Rasa', 'Lina', 'Tomas', 'Austeja', 'Darius', 'Greta', 'Nojus', 'Emily', 'James', 'Anna', 'Mark', 'Sofia', 'Lucas', 'Marie', 'Thomas', 'Elena', 'Nicolas'];
        $lastNames = ['Petrauskas', 'Kazlauskaite', 'Jankauskas', 'Vaitkus', 'Zukauskas', 'Kavaliauskas', 'Smith', 'Johnson', 'Brown', 'Miller', 'Dubois', 'Muller', 'Rossi', 'Garcia', 'Nowak'];

        for ($i = 1; $i <= self::TARGET_AUTHORS; $i++) {
            $name = self::PREFIX.' Autorius '.$firstNames[($i - 1) % count($firstNames)].' '.$lastNames[($i - 1) % count($lastNames)].' '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            Author::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => $this->uniqueSlug(Author::class, $name, 'autorius'),
                    'bio' => 'Autorius raso apie kultura, visuomenes pokycius ir siuolaikines bibliotekos skaitytoju temas.',
                ]
            );
        }

        return Author::query()->where('name', 'like', self::PREFIX.' Autorius %')->get();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Publisher>  $publishers
     * @param  Collection<int, Author>  $authors
     * @return Collection<int, Book>
     */
    private function ensureBooks(Collection $categories, Collection $publishers, Collection $authors): Collection
    {
        $existing = Book::query()->where('isbn', 'like', '97877%')->count();
        $missing = max(0, self::TARGET_BOOKS - $existing);
        $themes = ['Miestas', 'Atmintis', 'Algoritmai', 'Kelione', 'Sodas', 'Tyrimas', 'Horizontas', 'Pokalbiai', 'Slenkstis', 'Praktika'];

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
                    'description' => $this->bookDescription($category->name, $title),
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
    private function ensureStaff(Library $library, Collection $branches, User $admin): Collection
    {
        $staff = collect([$admin]);

        for ($i = 1; $i <= self::TARGET_STAFF; $i++) {
            $branch = $branches[($i - 1) % $branches->count()];
            $email = 'presentation.staff.'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'@example.com';
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

            $this->attachMembership($user, $library, $branch);
            $staff->push($user);
        }

        return $staff->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function ensureMembers(Library $library): Collection
    {
        $firstNames = ['Aiste', 'Milda', 'Lukas', 'Emilija', 'Rokas', 'Gabija', 'Dovile', 'Marius', 'Karolina', 'Tadas', 'Saule', 'Povilas'];
        $lastNames = ['Kazlauskaite', 'Petrauskas', 'Jankauskas', 'Rimkute', 'Vaitkus', 'Paulauskas', 'Zukauskaite', 'Mockus', 'Stankute', 'Balsevicius'];

        for ($i = 1; $i <= self::TARGET_MEMBERS; $i++) {
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

            $this->attachMembership($user, $library);
        }

        return User::query()
            ->whereIn('id', LibraryMembership::query()->where('library_id', $library->id)->pluck('user_id'))
            ->where('role', User::ROLE_MEMBER)
            ->get();
    }

    private function attachMembership(User $user, Library $library, ?Branch $branch = null): void
    {
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
    private function ensureCopies(Library $library, Collection $books, Collection $branches, Collection $locations, Collection $staff): Collection
    {
        $existing = BookCopy::query()
            ->where('library_id', $library->id)
            ->where('inventory_code', 'like', self::PREFIX.'-%')
            ->count();

        $missing = max(0, self::TARGET_COPIES - $existing);
        $rows = [];

        for ($i = $existing + 1; $i <= $existing + $missing; $i++) {
            $book = $books[($i - 1) % $books->count()];
            $branch = $branches[($i - 1) % $branches->count()];
            $branchLocations = $locations->where('branch_id', $branch->id)->values();
            $location = $branchLocations->isNotEmpty() ? $branchLocations[($i - 1) % $branchLocations->count()] : $locations[($i - 1) % $locations->count()];
            $status = $this->copyStatusForIndex($i);
            $createdAt = $this->safeTimestamp(now()->subDays(($i * 3) % 720));

            $rows[] = [
                'library_id' => $library->id,
                'book_id' => $book->id,
                'branch_id' => $branch->id,
                'location_id' => $location->id,
                'inventory_code' => self::PREFIX.'-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'qr_code' => self::PREFIX.'-QR-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'barcode' => self::PREFIX.'-BC-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'condition_status' => $this->conditionStatuses[$i % count($this->conditionStatuses)],
                'acquired_at' => $createdAt->toDateString(),
                'notes' => $this->copyNotes($status),
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
            ->where('inventory_code', 'like', self::PREFIX.'-%')
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
    private function ensureLoans(Library $library, Collection $copies, Collection $members, Collection $staff): void
    {
        $currentActive = Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_ACTIVE)->whereNull('returned_at')->count();
        $currentOverdue = Loan::query()->where('library_id', $library->id)->where('status', Loan::STATUS_OVERDUE)->whereNull('returned_at')->count();
        $currentTotal = Loan::query()->where('library_id', $library->id)->count();

        $activeMissing = max(0, self::TARGET_ACTIVE_LOANS - $currentActive);
        $overdueMissing = max(0, self::TARGET_OVERDUE_LOANS - $currentOverdue);
        $reservedCopyIds = Loan::query()->where('library_id', $library->id)->whereNull('returned_at')->pluck('book_copy_id')->all();
        $availableCopies = $copies->whereNotIn('id', $reservedCopyIds)->values();

        $this->insertCurrentLoans($library, $availableCopies->splice(0, $activeMissing), $members, $staff, Loan::STATUS_ACTIVE);
        $this->insertCurrentLoans($library, $availableCopies->splice(0, $overdueMissing), $members, $staff, Loan::STATUS_OVERDUE);

        $totalAfterCurrent = Loan::query()->where('library_id', $library->id)->count();
        $historicalMissing = max(0, self::TARGET_LOANS - $totalAfterCurrent);
        $rows = [];

        for ($i = 1; $i <= $historicalMissing; $i++) {
            $copy = $copies[($i - 1) % $copies->count()];
            $member = $members[($i - 1) % $members->count()];
            $employee = $staff[$i % $staff->count()];
            $borrowedAt = $this->safeTimestamp(now()->subDays(30 + (($currentTotal + $i) % 700))->setTime(9 + ($i % 8), ($i * 7) % 60));
            $dueAt = $this->safeTimestamp($borrowedAt->copy()->addDays(14 + ($i % 14)));
            $returnedAt = $this->safeTimestamp($borrowedAt->copy()->addDays(5 + ($i % 35)));
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
                'notes' => 'Istorine demonstracine paskola pristatymo statistikoms.',
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
    private function insertCurrentLoans(Library $library, Collection $copies, Collection $members, Collection $staff, string $status): void
    {
        $rows = [];
        $copyIds = [];

        foreach ($copies->values() as $i => $copy) {
            $borrowedAt = $status === Loan::STATUS_OVERDUE
                ? now()->subDays(35 + ($i % 60))->setTime(10, 0)
                : now()->subDays(1 + ($i % 20))->setTime(10, 0);
            $borrowedAt = $this->safeTimestamp($borrowedAt);
            $dueAt = $status === Loan::STATUS_OVERDUE
                ? now()->subDays(1 + ($i % 25))->setTime(18, 0)
                : now()->addDays(2 + ($i % 21))->setTime(18, 0);
            $dueAt = $this->safeTimestamp($dueAt);

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
                'notes' => $status === Loan::STATUS_OVERDUE ? 'Aktyvi veluojanti demonstracine paskola.' : 'Aktyvi demonstracine paskola.',
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
    private function ensureReservations(Library $library, Collection $books, Collection $branches, Collection $members): void
    {
        $currentTotal = Reservation::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::TARGET_RESERVATIONS - $currentTotal);
        $popularBooks = $books->take(30)->values();
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $book = $i <= 360 ? $popularBooks[($i - 1) % $popularBooks->count()] : $books[($i - 1) % $books->count()];
            $member = $members[($i - 1) % $members->count()];
            $branch = $branches[$i % $branches->count()];
            $reservedAt = $this->safeTimestamp(now()->subDays($i % 650)->setTime(8 + ($i % 10), ($i * 11) % 60));
            $status = $this->reservationStatusForIndex($i);
            $expiresAt = in_array($status, [Reservation::STATUS_RESERVED, Reservation::STATUS_EXPIRED], true)
                ? $this->safeTimestamp($status === Reservation::STATUS_RESERVED ? now()->addDays(1 + ($i % 8)) : $reservedAt->copy()->addDays(5))
                : null;

            $rows[] = [
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $member->id,
                'scope' => $i % 3 === 0 ? Reservation::SCOPE_BRANCH : Reservation::SCOPE_LIBRARY,
                'branch_id' => $i % 3 === 0 ? $branch->id : null,
                'status' => $status,
                'reserved_at' => $reservedAt,
                'expires_at' => $expiresAt,
                'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? $this->safeTimestamp($reservedAt->copy()->addDays(2)) : null,
                'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? $this->safeTimestamp($reservedAt->copy()->addDay()) : null,
                'notes' => $status === Reservation::STATUS_RESERVED ? 'Aktyvi demonstracine rezervacijos eile.' : 'Istorine demonstracine rezervacija.',
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
    private function ensureScanLogs(Library $library, Collection $copies, Collection $staff): void
    {
        $current = ScanLog::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::TARGET_SCAN_LOGS - $current);
        $types = ['info', 'loan', 'return', 'inventory'];
        $results = ['success', 'success', 'success', 'not_found', 'blocked', 'error'];
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $copy = $copies[($i - 1) % $copies->count()];
            $createdAt = $this->safeTimestamp(now()->subMinutes(($i * 17) % 900000));

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
    private function ensureAuditLogs(Library $library, Collection $books, Collection $copies, Collection $members, Collection $staff): void
    {
        $current = AuditLog::query()->where('library_id', $library->id)->count();
        $missing = max(0, self::TARGET_AUDIT_LOGS - $current);
        $actions = ['loan_issued', 'loan_returned', 'reservation_created', 'reservation_cancelled', 'book_updated', 'book_copy_status_changed', 'user_updated', 'library_staff_assigned'];
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $action = $actions[$i % count($actions)];
            $createdAt = $this->safeTimestamp(now()->subMinutes(($i * 37) % 1000000));
            [$auditableType, $auditableId, $label] = $this->auditTarget($action, $books, $copies, $members, $i);

            $rows[] = [
                'user_id' => $staff[$i % $staff->count()]->id,
                'library_id' => $library->id,
                'action' => $action,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'description' => $this->auditDescription($action, $label),
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
    private function ensureNotifications(Library $library, Collection $books, Collection $members, Collection $staff): void
    {
        $current = UserNotification::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->where('metadata->presentation_seed', true)
            ->count();
        $missing = max(0, self::TARGET_NOTIFICATIONS - $current);
        $types = array_keys(NotificationUiConfig::all());
        $rows = [];

        for ($i = 1; $i <= $missing; $i++) {
            $type = $types[$i % count($types)];
            $ui = NotificationUiConfig::for($type);
            $book = $books[($i - 1) % $books->count()];
            $createdAt = $this->safeTimestamp(now()->subMinutes(($i * 23) % 700000));

            $rows[] = [
                'user_id' => $members[($i - 1) % $members->count()]->id,
                'sent_by' => $staff[$i % $staff->count()]->id,
                'type' => $type,
                'title' => $this->notificationTitle($type),
                'message' => $this->notificationMessage($type, $book),
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

    private function printReport(Library $library): void
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

    private function copyStatusForIndex(int $i): string
    {
        return match (true) {
            $i % 50 === 0 => BookCopy::STATUS_LOST,
            $i % 33 === 0 => BookCopy::STATUS_MAINTENANCE,
            $i % 25 === 0 => BookCopy::STATUS_DAMAGED,
            $i % 20 === 0 => BookCopy::STATUS_WITHDRAWN,
            $i % 7 === 0 => BookCopy::STATUS_LOANED,
            default => BookCopy::STATUS_AVAILABLE,
        };
    }

    private function reservationStatusForIndex(int $i): string
    {
        return match ($i % 10) {
            0, 1, 2, 3 => Reservation::STATUS_RESERVED,
            4, 5 => Reservation::STATUS_FULFILLED,
            6, 7 => Reservation::STATUS_CANCELLED,
            default => Reservation::STATUS_EXPIRED,
        };
    }

    private function copyNotes(string $status): ?string
    {
        return match ($status) {
            BookCopy::STATUS_AVAILABLE => 'Kopija prieinama greitam isdavimui.',
            BookCopy::STATUS_LOANED => 'Kopija siuo metu naudojama skaitytojo.',
            BookCopy::STATUS_LOST => 'Pazymeta kaip prarasta inventorizacijos metu.',
            BookCopy::STATUS_DAMAGED => 'Reikia ivertinti bukle pries isdavima.',
            BookCopy::STATUS_MAINTENANCE => 'Tvarkoma arba paruosiama grizimui i fonda.',
            BookCopy::STATUS_WITHDRAWN => 'Nenaudojama aktyviame fonde.',
            default => null,
        };
    }

    private function bookDescription(string $category, string $title): string
    {
        return sprintf(
            '%s yra %s srities leidinys, tinkamas tiek kasdieniam skaitymui, tiek mokymuisi. Aprasyme aptariamos praktines situacijos, istorinis kontekstas ir temos, kurios padeda bibliotekos lankytojams greitai atsirinkti aktualu turini.',
            $title,
            mb_strtolower($category)
        );
    }

    /**
     * @param  class-string  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $value, string $fallback): string
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
    private function auditTarget(string $action, Collection $books, Collection $copies, Collection $members, int $i): array
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

    private function auditDescription(string $action, string $label): string
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

    private function notificationTitle(string $type): string
    {
        return match ($type) {
            'reservation_created' => 'Rezervacija sukurta',
            'reservation_queue_changed' => 'Pasikeite rezervacijos eile',
            'reservation_ready' => 'Rezervacija paruosta',
            'reservation_cancelled' => 'Rezervacija atsaukta',
            'reservation_fulfilled' => 'Rezervacija ivykdyta',
            'loan_overdue' => 'Veluojate grazinti knyga',
            'book_due_soon' => 'Arteja grazinimo terminas',
            'book_returned' => 'Knyga grazinta',
            'new_user' => 'Paskyra aktyvuota',
            'qr_scan' => 'QR kodas nuskaitytas',
            'report_ready' => 'Ataskaita paruosta',
            'issuance_summary' => 'Isdavimo suvestine',
            'system_warning' => 'Sistemos perspejimas',
            'system_error' => 'Sistemos klaida',
            'account_security' => 'Paskyros saugumas',
            default => 'Bibliotekos pranesimas',
        };
    }

    private function notificationMessage(string $type, Book $book): string
    {
        return match ($type) {
            'reservation_created' => 'Jusu rezervacija knygai "'.$book->title.'" sukurta.',
            'reservation_ready' => 'Knyga "'.$book->title.'" paruosta atsiemimui.',
            'reservation_cancelled' => 'Rezervacija knygai "'.$book->title.'" buvo atsaukta.',
            'loan_overdue' => 'Knygos "'.$book->title.'" grazinimo terminas jau praejo.',
            'book_due_soon' => 'Knyga "'.$book->title.'" turetu buti grazinta artimiausiomis dienomis.',
            'book_returned' => 'Knyga "'.$book->title.'" sekmingai grazinta.',
            default => 'Biblioteka atnaujino informacija apie knyga "'.$book->title.'".',
        };
    }

    private function safeTimestamp(CarbonInterface $timestamp): CarbonInterface
    {
        $safe = $timestamp->copy();

        if ((int) $safe->format('H') === 3) {
            return $safe->setTime(4, 0);
        }

        return $safe;
    }
}
