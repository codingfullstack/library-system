<?php

namespace Database\Seeders;

use App\Models\Author;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KaltinenuLibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now()->toImmutable();

            User::query()
                ->whereIn('email', [
                    'admin@kaltinenubiblioteka.lt',
                    'ieva@kaltinenubiblioteka.lt',
                    'tomas@kaltinenubiblioteka.lt',
                    'lukas.skaitytojas@example.com',
                    'emilija.skaitytoja@example.com',
                    'matas.skaitytojas@example.com',
                    'gabija.skaitytoja@example.com',
                    'saule.skaitytoja@example.com',
                    'karolina.skaitytoja@example.com',
                    'tadas.skaitytojas@example.com',
                    'aiste.skaitytoja@example.com',
                    'pijus.skaitytojas@example.com',
                    'greta.skaitytoja@example.com',
                ])->delete();

            Library::query()->where('code', 'KALT-ASTU-001')->get()->each->delete();

            $library = Library::create([
                'name' => 'Kaltinenu A. Stulginskio biblioteka',
                'code' => 'KALT-ASTU-001',
                'email' => 'info@kaltinenubiblioteka.lt',
                'phone' => '+37060000123',
                'address' => 'Varniu g. 12',
                'city' => 'Kaltinenai',
                'is_active' => true,
            ]);

            $mainBranch = Branch::create([
                'library_id' => $library->id,
                'name' => 'Pagrindinis skyrius',
                'code' => 'MAIN',
                'address' => 'Varniu g. 12',
                'city' => 'Kaltinenai',
            ]);

            $childrenBranch = Branch::create([
                'library_id' => $library->id,
                'name' => 'Vaiku ir jaunimo skyrius',
                'code' => 'KIDS',
                'address' => 'Varniu g. 12',
                'city' => 'Kaltinenai',
            ]);

            $fantasyLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $mainBranch->id,
                'name' => 'Fantastikos lentyna',
                'code' => 'LOC-FAN-01',
                'room' => '1',
                'shelf' => 'A-1',
                'description' => 'Fantastikos ir nuotykiu knygos',
            ]);

            $classicLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $mainBranch->id,
                'name' => 'Klasikos lentyna',
                'code' => 'LOC-KLAS-01',
                'room' => '1',
                'shelf' => 'B-2',
                'description' => 'Lietuviu ir pasaulio klasika',
            ]);

            $childrenLocation = Location::create([
                'library_id' => $library->id,
                'branch_id' => $childrenBranch->id,
                'name' => 'Jaunimo lentyna',
                'code' => 'LOC-YA-01',
                'room' => '2',
                'shelf' => 'C-3',
                'description' => 'Paaugliu ir jaunimo literatura',
            ]);

            $fictionCategory = Category::query()->firstOrCreate(
                ['slug' => 'grozine-literatura'],
                ['name' => 'Grozine literatura', 'description' => 'Romanai, apsakymai ir kita grozine literatura.']
            );
            $fantasyCategory = Category::query()->firstOrCreate(
                ['slug' => 'fantastika'],
                ['name' => 'Fantastika', 'description' => 'Fantastine, magine ir nuotykiu literatura.']
            );
            $classicCategory = Category::query()->firstOrCreate(
                ['slug' => 'klasika'],
                ['name' => 'Klasika', 'description' => 'Lietuviu ir pasaulio literaturos klasika.']
            );
            $youthCategory = Category::query()->firstOrCreate(
                ['slug' => 'jaunimo-literatura'],
                ['name' => 'Jaunimo literatura', 'description' => 'Knygos jauniesiems skaitytojams.']
            );

            $almaLittera = Publisher::query()->firstOrCreate(['name' => 'Alma littera'], ['country' => 'Lietuva']);
            $eridanas = Publisher::query()->firstOrCreate(['name' => 'Eridanas'], ['country' => 'Lietuva']);
            $vaga = Publisher::query()->firstOrCreate(['name' => 'Vaga'], ['country' => 'Lietuva']);
            $tytoAlba = Publisher::query()->firstOrCreate(['name' => 'Tyto alba'], ['country' => 'Lietuva']);

            $rowling = Author::query()->firstOrCreate(['name' => 'J. K. Rowling'], ['bio' => 'Britu rasytoja, geriausiai zinoma del Hario Poterio serijos.']);
            $sapkowski = Author::query()->firstOrCreate(['name' => 'Andrzej Sapkowski'], ['bio' => 'Lenku fantastikos rasytojas, isgarsines Raganiaus cikla.']);
            $putinas = Author::query()->firstOrCreate(['name' => 'Vincas Mykolaitis-Putinas'], ['bio' => 'Lietuviu rasytojas, poetas ir literaturos istorikas.']);
            $sruoga = Author::query()->firstOrCreate(['name' => 'Balys Sruoga'], ['bio' => 'Lietuviu rasytojas, dramaturgas ir literaturos kritikas.']);
            $green = Author::query()->firstOrCreate(['name' => 'John Green'], ['bio' => 'Amerikieciu jaunimo literaturos autorius.']);

            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@kaltinenubiblioteka.lt'],
                [
                    'library_id' => $library->id,
                    'name' => 'Kaltinenu bibliotekos administratorius',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'phone' => '+37061111111',
                    'membership_number' => null,
                    'is_active' => true,
                ]
            );

            $staffA = User::query()->updateOrCreate(
                ['email' => 'ieva@kaltinenubiblioteka.lt'],
                [
                    'library_id' => $library->id,
                    'name' => 'Ieva Jonaite',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => 'staff',
                    'phone' => '+37062222222',
                    'membership_number' => null,
                    'is_active' => true,
                ]
            );

            $staffB = User::query()->updateOrCreate(
                ['email' => 'tomas@kaltinenubiblioteka.lt'],
                [
                    'library_id' => $library->id,
                    'name' => 'Tomas Petrauskas',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => 'staff',
                    'phone' => '+37063333333',
                    'membership_number' => null,
                    'is_active' => true,
                ]
            );

            $member1 = $this->createMember($library, 'Lukas Petrauskas', 'lukas.skaitytojas@example.com', 'KAL-MEM-001', '+37064444444');
            $member2 = $this->createMember($library, 'Emilija Jankauskaite', 'emilija.skaitytoja@example.com', 'KAL-MEM-002', '+37065555555');
            $member3 = $this->createMember($library, 'Matas Vaitkus', 'matas.skaitytojas@example.com', 'KAL-MEM-003', '+37066666666');
            $member4 = $this->createMember($library, 'Gabija Rimkute', 'gabija.skaitytoja@example.com', 'KAL-MEM-004', '+37067777777');
            $member5 = $this->createMember($library, 'Saule Girdziute', 'saule.skaitytoja@example.com', 'KAL-MEM-005', '+37068888888');
            $member6 = $this->createMember($library, 'Karolina Butkeviciute', 'karolina.skaitytoja@example.com', 'KAL-MEM-006', '+37069900001');
            $member7 = $this->createMember($library, 'Tadas Veverskis', 'tadas.skaitytojas@example.com', 'KAL-MEM-007', '+37069900002');
            $member8 = $this->createMember($library, 'Aiste Maciulyte', 'aiste.skaitytoja@example.com', 'KAL-MEM-008', '+37069900003');
            $member9 = $this->createMember($library, 'Pijus Zabiela', 'pijus.skaitytojas@example.com', 'KAL-MEM-009', '+37069900004');
            $member10 = $this->createMember($library, 'Greta Simkute', 'greta.skaitytoja@example.com', 'KAL-MEM-010', '+37069900005');

            $allMembers = collect([
                $member1,
                $member2,
                $member3,
                $member4,
                $member5,
                $member6,
                $member7,
                $member8,
                $member9,
                $member10,
            ]);
            $employees = collect([$admin, $staffA, $staffB]);

            $hp1 = $this->firstOrCreateBook([
                'title' => 'Haris Poteris ir Isminties akmuo',
                'isbn' => '9786090155339',
                'description' => 'Pirmoji serijos dalis apie jauna burtininka Hari Poteri.',
                'publisher_id' => $almaLittera->id,
                'category_id' => $fantasyCategory->id,
                'publication_year' => 1997,
                'language' => 'lt',
                'page_count' => 352,
                'edition' => '1',
            ], [$rowling], [$fantasyCategory, $fictionCategory]);

            $witcher = $this->firstOrCreateBook([
                'title' => 'Raganius. Paskutinis noras',
                'isbn' => '9786094272211',
                'description' => 'Apsakymu rinkinys apie Geralta is Rivijos.',
                'publisher_id' => $eridanas->id,
                'category_id' => $fantasyCategory->id,
                'publication_year' => 1993,
                'language' => 'lt',
                'page_count' => 320,
                'edition' => '1',
            ], [$sapkowski], [$fantasyCategory]);

            $altoriu = $this->firstOrCreateBook([
                'title' => 'Altoriu sesely',
                'isbn' => '9785415011237',
                'description' => 'Vienas zymiausiu lietuviu literaturos romanu.',
                'publisher_id' => $vaga->id,
                'category_id' => $classicCategory->id,
                'publication_year' => 1933,
                'language' => 'lt',
                'page_count' => 600,
                'edition' => '1',
            ], [$putinas], [$classicCategory, $fictionCategory]);

            $dievuMiskas = $this->firstOrCreateBook([
                'title' => 'Dievu miskas',
                'isbn' => '9786094661550',
                'description' => 'Atsiminimu knyga apie gyvenima koncentracijos stovykloje.',
                'publisher_id' => $vaga->id,
                'category_id' => $classicCategory->id,
                'publication_year' => 1957,
                'language' => 'lt',
                'page_count' => 480,
                'edition' => '1',
            ], [$sruoga], [$classicCategory, $fictionCategory]);

            $faultInOurStars = $this->firstOrCreateBook([
                'title' => 'Del musu likimo ir zvaigzdziu kaltos',
                'isbn' => '9786094665800',
                'description' => 'Jaunimo romanas apie draugyste, meile ir netektis.',
                'publisher_id' => $tytoAlba->id,
                'category_id' => $youthCategory->id,
                'publication_year' => 2012,
                'language' => 'lt',
                'page_count' => 288,
                'edition' => '1',
            ], [$green], [$youthCategory, $fictionCategory]);

            $orwell = Author::query()->firstOrCreate(['name' => 'George Orwell'], ['bio' => 'Anglu rasytojas, zinomas del distopiniu romanu.']);
            $tolkien = Author::query()->firstOrCreate(['name' => 'J. R. R. Tolkien'], ['bio' => 'Britu fantastas, sukures Vidurzemes pasauli.']);
            $clear = Author::query()->firstOrCreate(['name' => 'James Clear'], ['bio' => 'Autorius, rasantis apie iprocius ir kasdienius pokycius.']);
            $kahneman = Author::query()->firstOrCreate(['name' => 'Daniel Kahneman'], ['bio' => 'Psichologas ir Nobelio premijos laureatas.']);
            $saintExupery = Author::query()->firstOrCreate(['name' => 'Antoine de Saint-Exupery'], ['bio' => 'Prancuzu autorius, parases Mazaji princa.']);

            $psychologyCategory = Category::query()->firstOrCreate(
                ['slug' => 'psichologija'],
                ['name' => 'Psichologija', 'description' => 'Psichologijos ir saviugdos knygos.']
            );
            $romanCategory = Category::query()->firstOrCreate(
                ['slug' => 'romanai'],
                ['name' => 'Romanai', 'description' => 'Grozines literaturos romanai.']
            );

            $book1984 = $this->firstOrCreateBook([
                'title' => '1984',
                'isbn' => '9786090142324',
                'description' => 'Distopinis romanas apie totalia kontrole ir tiesos perrasinejima.',
                'publisher_id' => $almaLittera->id,
                'category_id' => $classicCategory->id,
                'publication_year' => 1949,
                'language' => 'lt',
                'page_count' => 352,
                'edition' => '4',
            ], [$orwell], [$classicCategory, $romanCategory]);

            $hobbit = $this->firstOrCreateBook([
                'title' => 'Hobitas',
                'isbn' => '9786090135003',
                'description' => 'Bilbo Beggins nuotykiai kelyje i Vienisaji kalna.',
                'publisher_id' => $almaLittera->id,
                'category_id' => $fantasyCategory->id,
                'publication_year' => 1937,
                'language' => 'lt',
                'page_count' => 304,
                'edition' => '3',
            ], [$tolkien], [$fantasyCategory, $classicCategory]);

            $atomicHabits = $this->firstOrCreateBook([
                'title' => 'Atominiai iprociai',
                'isbn' => '9786090145100',
                'description' => 'Praktine knyga apie mazu kasdieniu iprociu itaka ilgalaikiams rezultatams.',
                'publisher_id' => $almaLittera->id,
                'category_id' => $psychologyCategory->id,
                'publication_year' => 2018,
                'language' => 'lt',
                'page_count' => 320,
                'edition' => '1',
            ], [$clear], [$psychologyCategory]);

            $thinking = $this->firstOrCreateBook([
                'title' => 'Mastymas, greitas ir letas',
                'isbn' => '9786090145455',
                'description' => 'Knyga apie sprendimu priemima, intuicija ir mastyma.',
                'publisher_id' => $almaLittera->id,
                'category_id' => $psychologyCategory->id,
                'publication_year' => 2011,
                'language' => 'lt',
                'page_count' => 512,
                'edition' => '1',
            ], [$kahneman], [$psychologyCategory]);

            $littlePrince = $this->firstOrCreateBook([
                'title' => 'Mazasis princas',
                'isbn' => '9786094270538',
                'description' => 'Poetine pasaka apie draugyste, meile ir atsakomybe.',
                'publisher_id' => $vaga->id,
                'category_id' => $youthCategory->id,
                'publication_year' => 1943,
                'language' => 'lt',
                'page_count' => 112,
                'edition' => '2',
            ], [$saintExupery], [$youthCategory, $fictionCategory]);

            $copies = collect([
                $this->createCopy($library, $hp1, $mainBranch, $fantasyLocation, 'KAL-HP1-001', 'QR-KAL-HP1-001', '9786090155331', BookCopy::STATUS_LOANED, 'good', '2023-09-01', 'Daznai skolinama knyga.'),
                $this->createCopy($library, $hp1, $mainBranch, $fantasyLocation, 'KAL-HP1-002', 'QR-KAL-HP1-002', '9786090155332', BookCopy::STATUS_AVAILABLE, 'good', '2023-09-01', null),
                $this->createCopy($library, $witcher, $mainBranch, $fantasyLocation, 'KAL-RAG-001', 'QR-KAL-RAG-001', '9786094272211', BookCopy::STATUS_MAINTENANCE, 'damaged', '2024-01-15', 'Lauzia nugarinele, issiusta tvarkymui.'),
                $this->createCopy($library, $witcher, $mainBranch, $fantasyLocation, 'KAL-RAG-002', 'QR-KAL-RAG-002', '9786094272212', BookCopy::STATUS_AVAILABLE, 'good', '2024-01-15', null),
                $this->createCopy($library, $altoriu, $mainBranch, $classicLocation, 'KAL-ALT-001', 'QR-KAL-ALT-001', '9785415011231', BookCopy::STATUS_DAMAGED, 'damaged', '2021-11-20', 'Apiplyses virselis.'),
                $this->createCopy($library, $altoriu, $mainBranch, $classicLocation, 'KAL-ALT-002', 'QR-KAL-ALT-002', '9785415011232', BookCopy::STATUS_AVAILABLE, 'worn', '2019-03-14', 'Senesnis egzempliorius.'),
                $this->createCopy($library, $dievuMiskas, $mainBranch, $classicLocation, 'KAL-DM-001', 'QR-KAL-DM-001', '9786094661551', BookCopy::STATUS_LOST, 'good', '2020-10-01', 'Nerastas po inventorizacijos.'),
                $this->createCopy($library, $faultInOurStars, $childrenBranch, $childrenLocation, 'KAL-YA-001', 'QR-KAL-YA-001', '9786094665801', BookCopy::STATUS_WITHDRAWN, 'worn', '2018-04-04', 'Per daug susidevejes, nurasytas.'),
                $this->createCopy($library, $faultInOurStars, $childrenBranch, $childrenLocation, 'KAL-YA-002', 'QR-KAL-YA-002', '9786094665802', BookCopy::STATUS_AVAILABLE, 'good', '2024-02-10', 'Laisva kopija, skirta greitam isdavimui rezervacijos eileje.'),
                $this->createCopy($library, $book1984, $mainBranch, $classicLocation, 'KAL-1984-001', 'QR-KAL-1984-001', '9786090142321', BookCopy::STATUS_AVAILABLE, 'good', '2023-01-15', null),
                $this->createCopy($library, $book1984, $mainBranch, $classicLocation, 'KAL-1984-002', 'QR-KAL-1984-002', '9786090142322', BookCopy::STATUS_AVAILABLE, 'good', '2023-01-15', null),
                $this->createCopy($library, $hobbit, $mainBranch, $fantasyLocation, 'KAL-HOB-001', 'QR-KAL-HOB-001', '9786090135001', BookCopy::STATUS_AVAILABLE, 'good', '2022-08-20', null),
                $this->createCopy($library, $hobbit, $mainBranch, $fantasyLocation, 'KAL-HOB-002', 'QR-KAL-HOB-002', '9786090135002', BookCopy::STATUS_AVAILABLE, 'worn', '2022-08-20', null),
                $this->createCopy($library, $atomicHabits, $mainBranch, $classicLocation, 'KAL-AH-001', 'QR-KAL-AH-001', '9786090145101', BookCopy::STATUS_AVAILABLE, 'good', '2024-06-10', null),
                $this->createCopy($library, $thinking, $mainBranch, $classicLocation, 'KAL-TF-001', 'QR-KAL-TF-001', '9786090145451', BookCopy::STATUS_AVAILABLE, 'good', '2024-04-08', null),
                $this->createCopy($library, $littlePrince, $childrenBranch, $childrenLocation, 'KAL-MP-001', 'QR-KAL-MP-001', '9786094270531', BookCopy::STATUS_AVAILABLE, 'good', '2023-11-09', null),
                $this->createCopy($library, $littlePrince, $childrenBranch, $childrenLocation, 'KAL-MP-002', 'QR-KAL-MP-002', '9786094270532', BookCopy::STATUS_AVAILABLE, 'worn', '2023-11-09', null),
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
                'status' => 'active',
                'renewal_count' => 0,
                'notes' => 'Skolinimas testavimui per mobili programele.',
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
                'status' => 'returned',
                'renewal_count' => 1,
                'notes' => 'Jau grazinta demonstracine paskola.',
            ]);

            Reservation::create([
                'library_id' => $library->id,
                'book_id' => $faultInOurStars->id,
                'user_id' => $member4->id,
                'status' => Reservation::STATUS_RESERVED,
                'reserved_at' => $now->subHours(3),
                'expires_at' => $now->addDays(3),
                'fulfilled_at' => null,
                'cancelled_at' => null,
                'notes' => 'Laukia laisvo egzemplioriaus jaunimo skyriuje.',
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
                'notes' => 'Atsaukta demonstracine rezervacija.',
            ]);

            $this->recordHistory($copies[0], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-05-12 09:00:00'));
            $this->recordHistory($copies[0], $staffA, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOANED, 'issued', 'Egzempliorius siandien isduotas skaitytojui.', $now->subHours(2));
            $this->recordHistory($copies[2], $staffB, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-08-14 10:00:00'));
            $this->recordHistory($copies[2], $staffB, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_MAINTENANCE, 'sent_to_maintenance', 'Issiustas tvarkyti del pazeidimu.', $now->subDays(11));
            $this->recordHistory($copies[4], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-07-03 14:00:00'));
            $this->recordHistory($copies[4], $staffA, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_DAMAGED, 'marked_damaged', 'Apziuros metu pazymeta kaip sugadinta.', $now->subMonths(2)->subDays(4));
            $this->recordHistory($copies[6], $admin, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-06-06 11:00:00'));
            $this->recordHistory($copies[6], $admin, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOST, 'marked_lost', 'Inventorizacijos metu egzempliorius nerastas.', $now->subMonths(5));
            $this->recordHistory($copies[7], $admin, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-05-28 12:30:00'));
            $this->recordHistory($copies[7], $admin, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_WITHDRAWN, 'withdrawn', 'Nurasyta del susidevejimo.', $now->subMonths(7));
            $this->recordHistory($copies[8], $staffA, null, BookCopy::STATUS_AVAILABLE, 'created', 'Egzempliorius sukurtas sistemoje.', CarbonImmutable::parse('2025-09-01 09:15:00'));

            $this->seedHistoricalLoans($library, $copies->slice(9)->values(), $allMembers, $employees, $now);
            $this->seedHistoricalReservations($library, collect([
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

    private function createMember(Library $library, string $name, string $email, string $membershipNumber, string $phone): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'library_id' => $library->id,
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'phone' => $phone,
                'membership_number' => $membershipNumber,
                'is_active' => true,
            ]
        );
    }

    private function firstOrCreateBook(array $data, array $authors, array $categories): Book
    {
        $book = Book::query()->firstOrCreate(
            ['isbn' => $data['isbn']],
            $data + ['subtitle' => null, 'cover_image' => null]
        );

        $book->authors()->syncWithoutDetaching(collect($authors)->pluck('id')->all());
        $book->categories()->syncWithoutDetaching(collect($categories)->pluck('id')->all());

        return $book;
    }

    private function createCopy(
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

    private function recordHistory(
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
            'changed_at' => $changedAt ?? now()->subDays(rand(1, 90)),
        ]);

        if ($changedAt && $changedAt->gt($copy->updated_at?->toImmutable() ?? CarbonImmutable::parse($copy->created_at))) {
            $copy->forceFill([
                'updated_at' => $changedAt,
            ])->saveQuietly();
        }
    }

    private function seedHistoricalLoans(Library $library, $copies, $members, $employees, CarbonImmutable $now): void
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
                'status' => 'returned',
                'renewal_count' => $monthOffset % 2,
                'notes' => 'Istorinis demonstracinis isdavimas testavimui.',
            ]);

            $this->recordHistory($copy, $employee, BookCopy::STATUS_AVAILABLE, BookCopy::STATUS_LOANED, 'issued', 'Istorinis isdavimas demonstraciniams duomenims.', $borrowedAt);
            $this->recordHistory($copy, $employee, BookCopy::STATUS_LOANED, BookCopy::STATUS_AVAILABLE, 'returned', 'Istorinis grazinimas demonstraciniams duomenims.', $returnedAt);

            $copy->forceFill([
                'status' => BookCopy::STATUS_AVAILABLE,
                'updated_at' => $returnedAt,
            ])->saveQuietly();
        }
    }

    private function seedHistoricalReservations(Library $library, $books, $members, CarbonImmutable $now): void
    {
        foreach (range(1, 12) as $monthOffset) {
            $book = $books[($monthOffset - 1) % $books->count()];
            $member = $members[($monthOffset - 1) % $members->count()];
            $reservedAt = $now->subMonths($monthOffset)->setTime(9, 30)->subDays($monthOffset % 4);

            $status = match ($monthOffset % 4) {
                0 => Reservation::STATUS_FULFILLED,
                1 => Reservation::STATUS_CANCELLED,
                2 => Reservation::STATUS_EXPIRED,
                default => Reservation::STATUS_RESERVED,
            };

            Reservation::create([
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $member->id,
                'status' => $status,
                'reserved_at' => $reservedAt,
                'expires_at' => in_array($status, [Reservation::STATUS_RESERVED, Reservation::STATUS_EXPIRED], true)
                    ? $reservedAt->addDays(5)
                    : null,
                'fulfilled_at' => $status === Reservation::STATUS_FULFILLED ? $reservedAt->addDays(2) : null,
                'cancelled_at' => $status === Reservation::STATUS_CANCELLED ? $reservedAt->addDay() : null,
                'notes' => 'Istorine rezervacija dashboard testavimui.',
            ]);
        }
    }
}
