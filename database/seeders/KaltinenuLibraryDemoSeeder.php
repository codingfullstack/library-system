<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KaltinenuLibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            /*
            |--------------------------------------------------------------------------
            | 0. Jei šita demo biblioteka jau egzistuoja - išvalom jos priklausomus duomenis
            |--------------------------------------------------------------------------
            */
            $existingLibrary = DB::table('libraries')
                ->where('code', 'KALT-ASTU-001')
                ->first();

            if ($existingLibrary) {
                $libraryId = $existingLibrary->id;

                $bookCopyIds = DB::table('book_copies')
                    ->where('library_id', $libraryId)
                    ->pluck('id');

                $branchIds = DB::table('branches')
                    ->where('library_id', $libraryId)
                    ->pluck('id');

                DB::table('scan_logs')->where('library_id', $libraryId)->delete();
                DB::table('reservations')->where('library_id', $libraryId)->delete();
                DB::table('loans')->where('library_id', $libraryId)->delete();
                DB::table('users')->where('library_id', $libraryId)->delete();

                if ($bookCopyIds->isNotEmpty()) {
                    DB::table('book_copies')->whereIn('id', $bookCopyIds)->delete();
                }

                if ($branchIds->isNotEmpty()) {
                    DB::table('locations')->whereIn('branch_id', $branchIds)->delete();
                    DB::table('branches')->whereIn('id', $branchIds)->delete();
                }

                DB::table('libraries')->where('id', $libraryId)->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | 1. Library
            |--------------------------------------------------------------------------
            */
            $libraryId = DB::table('libraries')->insertGetId([
                'name' => 'Kaltinėnų A. Stulginskio biblioteka',
                'code' => 'KALT-ASTU-001',
                'email' => 'info@kaltinenubiblioteka.lt',
                'phone' => '+37060000123',
                'address' => 'Varnių g. 12',
                'city' => 'Kaltinėnai',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. Branch
            |--------------------------------------------------------------------------
            */
            $branchId = DB::table('branches')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Pagrindinis skyrius',
                'code' => 'MAIN',
                'address' => 'Varnių g. 12',
                'city' => 'Kaltinėnai',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. Locations
            |--------------------------------------------------------------------------
            */
            $fantasyLocationId = DB::table('locations')->insertGetId([
                'library_id' => $libraryId,
                'branch_id' => $branchId,
                'name' => 'Fantastikos lentyna',
                'code' => 'LOC-FAN-01',
                'room' => '1',
                'shelf' => 'A-1',
                'description' => 'Fantastikos ir nuotykių knygos',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $classicLocationId = DB::table('locations')->insertGetId([
                'library_id' => $libraryId,
                'branch_id' => $branchId,
                'name' => 'Klasikos lentyna',
                'code' => 'LOC-KLAS-01',
                'room' => '1',
                'shelf' => 'B-2',
                'description' => 'Lietuvių ir pasaulio literatūros klasika',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4. Categories
            |--------------------------------------------------------------------------
            */
            $fictionCategory = DB::table('categories')
                ->where('slug', 'grozine-literatura')
                ->first();

            if (!$fictionCategory) {
                $fictionCategoryId = DB::table('categories')->insertGetId([
                    'name' => 'Grožinė literatūra',
                    'slug' => 'grozine-literatura',
                    'description' => 'Romanai, apsakymai ir kita grožinė literatūra.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $fictionCategoryId = $fictionCategory->id;
            }

            $fantasyCategory = DB::table('categories')
                ->where('slug', 'fantastika')
                ->first();

            if (!$fantasyCategory) {
                $fantasyCategoryId = DB::table('categories')->insertGetId([
                    'name' => 'Fantastika',
                    'slug' => 'fantastika',
                    'description' => 'Fantastinė, maginė ir nuotykių literatūra.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $fantasyCategoryId = $fantasyCategory->id;
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Publishers
            |--------------------------------------------------------------------------
            | Pagal tavo lentelę: name, country
            |--------------------------------------------------------------------------
            */
            $almaLittera = DB::table('publishers')->where('name', 'Alma littera')->first();
            $almaLitteraId = $almaLittera
                ? $almaLittera->id
                : DB::table('publishers')->insertGetId([
                    'name' => 'Alma littera',
                    'country' => 'Lietuva',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $eridanas = DB::table('publishers')->where('name', 'Eridanas')->first();
            $eridanasId = $eridanas
                ? $eridanas->id
                : DB::table('publishers')->insertGetId([
                    'name' => 'Eridanas',
                    'country' => 'Lietuva',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $vaga = DB::table('publishers')->where('name', 'Vaga')->first();
            $vagaId = $vaga
                ? $vaga->id
                : DB::table('publishers')->insertGetId([
                    'name' => 'Vaga',
                    'country' => 'Lietuva',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            /*
            |--------------------------------------------------------------------------
            | 6. Authors
            |--------------------------------------------------------------------------
            | Pagal tavo lentelę: name, bio
            |--------------------------------------------------------------------------
            */
            $rowling = DB::table('authors')->where('name', 'J. K. Rowling')->first();
            $rowlingId = $rowling
                ? $rowling->id
                : DB::table('authors')->insertGetId([
                    'name' => 'J. K. Rowling',
                    'bio' => 'Britų rašytoja, geriausiai žinoma dėl Hario Poterio knygų serijos.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $sapkowski = DB::table('authors')->where('name', 'Andrzej Sapkowski')->first();
            $sapkowskiId = $sapkowski
                ? $sapkowski->id
                : DB::table('authors')->insertGetId([
                    'name' => 'Andrzej Sapkowski',
                    'bio' => 'Lenkų fantastikos rašytojas, išgarsėjęs „Raganiaus“ knygų serija.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $putinas = DB::table('authors')->where('name', 'Vincas Mykolaitis-Putinas')->first();
            $putinasId = $putinas
                ? $putinas->id
                : DB::table('authors')->insertGetId([
                    'name' => 'Vincas Mykolaitis-Putinas',
                    'bio' => 'Lietuvių rašytojas, poetas ir literatūros istorikas.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $sruoga = DB::table('authors')->where('name', 'Balys Sruoga')->first();
            $sruogaId = $sruoga
                ? $sruoga->id
                : DB::table('authors')->insertGetId([
                    'name' => 'Balys Sruoga',
                    'bio' => 'Lietuvių rašytojas, dramaturgas ir literatūros kritikas.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Users
            |--------------------------------------------------------------------------
            */
            $adminId = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Kaltinėnų bibliotekos administratorius',
                'email' => 'admin@kaltinenubiblioteka.lt',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+37061111111',
                'membership_number' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $staff1Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Ieva Jonaitė',
                'email' => 'ieva@kaltinenubiblioteka.lt',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'staff',
                'phone' => '+37062222222',
                'membership_number' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $staff2Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Tomas Petrauskas',
                'email' => 'tomas@kaltinenubiblioteka.lt',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'staff',
                'phone' => '+37063333333',
                'membership_number' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $member1Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Lukas Petrauskas',
                'email' => 'lukas.skaitytojas@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'member',
                'phone' => '+37064444444',
                'membership_number' => 'KAL-MEM-001',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $member2Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Emilija Jankauskaitė',
                'email' => 'emilija.skaitytoja@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'member',
                'phone' => '+37065555555',
                'membership_number' => 'KAL-MEM-002',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $member3Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Matas Vaitkus',
                'email' => 'matas.skaitytojas@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'member',
                'phone' => '+37066666666',
                'membership_number' => 'KAL-MEM-003',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $member4Id = DB::table('users')->insertGetId([
                'library_id' => $libraryId,
                'name' => 'Gabija Rimkutė',
                'email' => 'gabija.skaitytoja@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'role' => 'member',
                'phone' => '+37067777777',
                'membership_number' => 'KAL-MEM-004',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 8. Books
            |--------------------------------------------------------------------------
            */
            $hp1Id = $this->firstOrCreateBook([
                'title' => 'Haris Poteris ir Išminties akmuo',
                'subtitle' => null,
                'isbn' => '9786090155339',
                'description' => 'Pirmoji serijos dalis apie jauną burtininką Harį Poterį.',
                'publisher_id' => $almaLitteraId,
                'category_id' => $fantasyCategoryId,
                'publication_year' => 1997,
                'language' => 'lt',
                'page_count' => 352,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $hp2Id = $this->firstOrCreateBook([
                'title' => 'Haris Poteris ir Paslapčių kambarys',
                'subtitle' => null,
                'isbn' => '9786090155346',
                'description' => 'Antroji Hario Poterio nuotykių dalis.',
                'publisher_id' => $almaLitteraId,
                'category_id' => $fantasyCategoryId,
                'publication_year' => 1998,
                'language' => 'lt',
                'page_count' => 288,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $witcher1Id = $this->firstOrCreateBook([
                'title' => 'Raganius. Paskutinis noras',
                'subtitle' => null,
                'isbn' => '9786094272211',
                'description' => 'Apsakymų rinkinys apie Geraltą iš Rivijos.',
                'publisher_id' => $eridanasId,
                'category_id' => $fantasyCategoryId,
                'publication_year' => 1993,
                'language' => 'lt',
                'page_count' => 320,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $witcher2Id = $this->firstOrCreateBook([
                'title' => 'Raganius. Likimo kalavijas',
                'subtitle' => null,
                'isbn' => '9786094272228',
                'description' => 'Antra „Raganiaus“ knyga.',
                'publisher_id' => $eridanasId,
                'category_id' => $fantasyCategoryId,
                'publication_year' => 1992,
                'language' => 'lt',
                'page_count' => 336,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $altoriuId = $this->firstOrCreateBook([
                'title' => 'Altorių šešėly',
                'subtitle' => null,
                'isbn' => '9785415011237',
                'description' => 'Vienas žymiausių lietuvių literatūros romanų.',
                'publisher_id' => $vagaId,
                'category_id' => $fictionCategoryId,
                'publication_year' => 1933,
                'language' => 'lt',
                'page_count' => 600,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $dievuMiskasId = $this->firstOrCreateBook([
                'title' => 'Dievų miškas',
                'subtitle' => null,
                'isbn' => '9786094661550',
                'description' => 'Atsiminimų knyga apie gyvenimą koncentracijos stovykloje.',
                'publisher_id' => $vagaId,
                'category_id' => $fictionCategoryId,
                'publication_year' => 1957,
                'language' => 'lt',
                'page_count' => 480,
                'edition' => '1',
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 9. Book authors pivot
            |--------------------------------------------------------------------------
            */
            $this->attachAuthorToBook($hp1Id, $rowlingId, $now);
            $this->attachAuthorToBook($hp2Id, $rowlingId, $now);
            $this->attachAuthorToBook($witcher1Id, $sapkowskiId, $now);
            $this->attachAuthorToBook($witcher2Id, $sapkowskiId, $now);
            $this->attachAuthorToBook($altoriuId, $putinasId, $now);
            $this->attachAuthorToBook($dievuMiskasId, $sruogaId, $now);

            /*
      |--------------------------------------------------------------------------
      | 10. Book copies
      |--------------------------------------------------------------------------
      */
            $hp1Copy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $hp1Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-HP1-001',
                'qr_code' => 'QR-KAL-HP1-001',
                'barcode' => '9786090155331',
                'status' => 'loaned',
                'condition_status' => 'good',
                'acquired_at' => '2023-09-01',
                'price' => 18.99,
                'notes' => 'Dažnai skolinama knyga.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $hp1Copy2Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $hp1Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-HP1-002',
                'qr_code' => 'QR-KAL-HP1-002',
                'barcode' => '9786090155332',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2023-09-01',
                'price' => 18.99,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $hp2Copy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $hp2Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-HP2-001',
                'qr_code' => 'QR-KAL-HP2-001',
                'barcode' => '9786090155341',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2023-10-01',
                'price' => 17.99,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $witcher1Copy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $witcher1Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-RAG-001',
                'qr_code' => 'QR-KAL-RAG-001',
                'barcode' => '9786094272211',
                'status' => 'loaned',
                'condition_status' => 'good',
                'acquired_at' => '2024-01-15',
                'price' => 19.90,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $witcher1Copy2Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $witcher1Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-RAG-002',
                'qr_code' => 'QR-KAL-RAG-002',
                'barcode' => '9786094272212',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2024-01-15',
                'price' => 19.90,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $witcher2Copy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $witcher2Id,
                'branch_id' => $branchId,
                'location_id' => $fantasyLocationId,
                'inventory_code' => 'KAL-RAG2-001',
                'qr_code' => 'QR-KAL-RAG2-001',
                'barcode' => '9786094272221',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2024-01-20',
                'price' => 19.90,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $altoriuCopy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $altoriuId,
                'branch_id' => $branchId,
                'location_id' => $classicLocationId,
                'inventory_code' => 'KAL-ALT-001',
                'qr_code' => 'QR-KAL-ALT-001',
                'barcode' => '9785415011231',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2021-11-20',
                'price' => 14.50,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $altoriuCopy2Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $altoriuId,
                'branch_id' => $branchId,
                'location_id' => $classicLocationId,
                'inventory_code' => 'KAL-ALT-002',
                'qr_code' => 'QR-KAL-ALT-002',
                'barcode' => '9785415011232',
                'status' => 'available',
                'condition_status' => 'worn',
                'acquired_at' => '2019-03-14',
                'price' => 12.00,
                'notes' => 'Senesnis egzempliorius.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $dievuMiskasCopy1Id = DB::table('book_copies')->insertGetId([
                'library_id' => $libraryId,
                'book_id' => $dievuMiskasId,
                'branch_id' => $branchId,
                'location_id' => $classicLocationId,
                'inventory_code' => 'KAL-DM-001',
                'qr_code' => 'QR-KAL-DM-001',
                'barcode' => '9786094661551',
                'status' => 'available',
                'condition_status' => 'good',
                'acquired_at' => '2020-10-01',
                'price' => 13.20,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 11. Loans
            |--------------------------------------------------------------------------
            | borrowed_at / due_at / returned_at rašom kaip DATĄ
            |--------------------------------------------------------------------------
            */
            DB::table('loans')->insert([
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $hp1Copy1Id,
                    'user_id' => $member1Id,
                    'issued_by' => $staff1Id,
                    'received_by' => null,
                    'borrowed_at' => now()->subDays(4)->toDateString(),
                    'due_at' => now()->addDays(10)->toDateString(),
                    'returned_at' => null,
                    'status' => 'active',
                    'renewal_count' => 0,
                    'notes' => 'Skolinimas testavimui per mobilią programėlę.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $witcher1Copy1Id,
                    'user_id' => $member2Id,
                    'issued_by' => $staff2Id,
                    'received_by' => null,
                    'borrowed_at' => now()->subDays(2)->toDateString(),
                    'due_at' => now()->addDays(12)->toDateString(),
                    'returned_at' => null,
                    'status' => 'active',
                    'renewal_count' => 0,
                    'notes' => 'Aktyvi paskola.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $altoriuCopy2Id,
                    'user_id' => $member3Id,
                    'issued_by' => $staff1Id,
                    'received_by' => $staff2Id,
                    'borrowed_at' => now()->subDays(20)->toDateString(),
                    'due_at' => now()->subDays(6)->toDateString(),
                    'returned_at' => now()->subDays(5)->toDateString(),
                    'status' => 'returned',
                    'renewal_count' => 1,
                    'notes' => 'Grąžinta laiku.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 12. Reservations
            |--------------------------------------------------------------------------
            | Naudoju 'cancelled', nes tokį statusą tikrai matei savo DB.
            |--------------------------------------------------------------------------
            */
            DB::table('reservations')->insert([
                'library_id' => $libraryId,
                'book_id' => $witcher2Id,
                'user_id' => $member4Id,
                'status' => 'cancelled',
                'reserved_at' => now()->subDays(1)->toDateTimeString(),
                'expires_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => now()->toDateTimeString(),
                'notes' => 'Atšaukta demonstracinė rezervacija.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 13. Scan logs
            |--------------------------------------------------------------------------
            */
            DB::table('scan_logs')->insert([
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $hp1Copy1Id,
                    'user_id' => $staff1Id,
                    'scan_value' => 'QR-KAL-HP1-001',
                    'scan_type' => 'loan',
                    'result' => 'success',
                    'device_info' => 'Samsung A52',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $witcher1Copy2Id,
                    'user_id' => $staff2Id,
                    'scan_value' => 'QR-KAL-RAG-002',
                    'scan_type' => 'inventory',
                    'result' => 'success',
                    'device_info' => 'Samsung A55',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'library_id' => $libraryId,
                    'book_copy_id' => $dievuMiskasCopy1Id,
                    'user_id' => $adminId,
                    'scan_value' => 'QR-KAL-DM-001',
                    'scan_type' => 'inventory',
                    'result' => 'success',
                    'device_info' => 'Web Scanner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }

    private function firstOrCreateBook(array $data): int
    {
        $existing = DB::table('books')->where('isbn', $data['isbn'])->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('books')->insertGetId($data);
    }

    private function attachAuthorToBook(int $bookId, int $authorId, $now): void
    {
        $exists = DB::table('book_author')
            ->where('book_id', $bookId)
            ->where('author_id', $authorId)
            ->exists();

        if (!$exists) {
            DB::table('book_author')->insert([
                'book_id' => $bookId,
                'author_id' => $authorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}