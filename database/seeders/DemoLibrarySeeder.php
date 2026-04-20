<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $libraryX = Library::create([
            'name' => 'Library X',
            'code' => 'LIB-X',
            'email' => 'x@library.test',
            'phone' => '+37060000001',
            'address' => 'X Street 1',
            'city' => 'Vilnius',
            'is_active' => true,
        ]);

        $libraryY = Library::create([
            'name' => 'Library Y',
            'code' => 'LIB-Y',
            'email' => 'y@library.test',
            'phone' => '+37060000002',
            'address' => 'Y Street 2',
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

        $adminX = User::create([
            'library_id' => $libraryX->id,
            'name' => 'Admin X',
            'email' => 'adminx@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+37061111111',
            'membership_number' => 'ADM-X-001',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staffX = User::create([
            'library_id' => $libraryX->id,
            'name' => 'Staff X',
            'email' => 'staffx@test.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'phone' => '+37061111112',
            'membership_number' => 'STF-X-001',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $adminY = User::create([
            'library_id' => $libraryY->id,
            'name' => 'Admin Y',
            'email' => 'adminy@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+37062222221',
            'membership_number' => 'ADM-Y-001',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staffY = User::create([
            'library_id' => $libraryY->id,
            'name' => 'Staff Y',
            'email' => 'staffy@test.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'phone' => '+37062222222',
            'membership_number' => 'STF-Y-001',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $membersX = User::factory()
            ->count(30)
            ->member()
            ->create([
                'library_id' => $libraryX->id,
            ]);

        $membersY = User::factory()
            ->count(30)
            ->member()
            ->create([
                'library_id' => $libraryY->id,
            ]);

        $categories = Category::factory()->count(10)->create();
        $publishers = Publisher::factory()->count(12)->create();
        $authors = Author::factory()->count(40)->create();

        $books = Book::factory()
            ->count(60)
            ->create([
                'category_id' => fn () => $categories->random()->id,
                'publisher_id' => fn () => $publishers->random()->id,
            ]);

        foreach ($books as $book) {
            $book->authors()->attach(
                $authors->random(rand(1, 3))->pluck('id')->toArray()
            );
        }

        $branchesX = Branch::factory()
            ->count(3)
            ->create([
                'library_id' => $libraryX->id,
            ]);

        $branchesY = Branch::factory()
            ->count(3)
            ->create([
                'library_id' => $libraryY->id,
            ]);

        $locationsX = collect();
        foreach ($branchesX as $branch) {
            $locationsX = $locationsX->merge(
                Location::factory()->count(5)->create([
                    'library_id' => $libraryX->id,
                    'branch_id' => $branch->id,
                ])
            );
        }

        $locationsY = collect();
        foreach ($branchesY as $branch) {
            $locationsY = $locationsY->merge(
                Location::factory()->count(5)->create([
                    'library_id' => $libraryY->id,
                    'branch_id' => $branch->id,
                ])
            );
        }

        $copiesX = collect();
        foreach ($books->random(35) as $book) {
            $count = rand(1, 4);

            for ($i = 0; $i < $count; $i++) {
                $branch = $branchesX->random();
                $location = $locationsX->where('branch_id', $branch->id)->random();

                $copiesX->push(BookCopy::create([
                    'library_id' => $libraryX->id,
                    'book_id' => $book->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'inventory_code' => 'X-' . strtoupper(Str::random(8)),
                    'qr_code' => 'QR-X-' . strtoupper(Str::random(10)),
                    'barcode' => rand(0, 1) ? fake()->unique()->numerify('###########') : null,
                    'status' => 'available',
                    'condition_status' => fake()->randomElement(['new', 'good', 'good', 'worn']),
                    'acquired_at' => fake()->date(),
                    'price' => fake()->randomFloat(2, 5, 100),
                    'notes' => fake()->optional()->sentence(),
                ]));
            }
        }

        $copiesY = collect();
        foreach ($books->random(35) as $book) {
            $count = rand(1, 4);

            for ($i = 0; $i < $count; $i++) {
                $branch = $branchesY->random();
                $location = $locationsY->where('branch_id', $branch->id)->random();

                $copiesY->push(BookCopy::create([
                    'library_id' => $libraryY->id,
                    'book_id' => $book->id,
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'inventory_code' => 'Y-' . strtoupper(Str::random(8)),
                    'qr_code' => 'QR-Y-' . strtoupper(Str::random(10)),
                    'barcode' => rand(0, 1) ? fake()->unique()->numerify('###########') : null,
                    'status' => 'available',
                    'condition_status' => fake()->randomElement(['new', 'good', 'good', 'worn']),
                    'acquired_at' => fake()->date(),
                    'price' => fake()->randomFloat(2, 5, 100),
                    'notes' => fake()->optional()->sentence(),
                ]));
            }
        }

        foreach ($copiesX->random(min(25, $copiesX->count())) as $copy) {
            $returned = fake()->boolean(40);
            $borrowedAt = fake()->dateTimeBetween('-45 days', '-5 days');
            $dueAt = (clone $borrowedAt)->modify('+14 days');
            $status = $returned ? 'returned' : (now()->gt($dueAt) ? 'overdue' : 'active');

            Loan::create([
                'library_id' => $libraryX->id,
                'book_copy_id' => $copy->id,
                'user_id' => $membersX->random()->id,
                'issued_by' => fake()->randomElement([$adminX->id, $staffX->id]),
                'received_by' => $returned ? fake()->randomElement([$adminX->id, $staffX->id]) : null,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => $returned ? fake()->dateTimeBetween($borrowedAt, 'now') : null,
                'status' => $status,
                'renewal_count' => rand(0, 2),
                'notes' => fake()->optional()->sentence(),
            ]);

            if (! $returned) {
                $copy->update([
                    'status' => $status === 'active' || $status === 'overdue' ? 'loaned' : $copy->status,
                ]);
            }
        }

        foreach ($copiesY->random(min(25, $copiesY->count())) as $copy) {
            $returned = fake()->boolean(40);
            $borrowedAt = fake()->dateTimeBetween('-45 days', '-5 days');
            $dueAt = (clone $borrowedAt)->modify('+14 days');
            $status = $returned ? 'returned' : (now()->gt($dueAt) ? 'overdue' : 'active');

            Loan::create([
                'library_id' => $libraryY->id,
                'book_copy_id' => $copy->id,
                'user_id' => $membersY->random()->id,
                'issued_by' => fake()->randomElement([$adminY->id, $staffY->id]),
                'received_by' => $returned ? fake()->randomElement([$adminY->id, $staffY->id]) : null,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'returned_at' => $returned ? fake()->dateTimeBetween($borrowedAt, 'now') : null,
                'status' => $status,
                'renewal_count' => rand(0, 2),
                'notes' => fake()->optional()->sentence(),
            ]);

            if (! $returned) {
                $copy->update([
                    'status' => $status === 'active' || $status === 'overdue' ? 'loaned' : $copy->status,
                ]);
            }
        }

        foreach ($books->random(15) as $book) {
            Reservation::create([
                'library_id' => $libraryX->id,
                'book_id' => $book->id,
                'user_id' => $membersX->random()->id,
                'status' => fake()->randomElement(['reserved', 'fulfilled', 'cancelled', 'expired']),
                'reserved_at' => fake()->dateTimeBetween('-20 days', 'now'),
                'expires_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
                'fulfilled_at' => null,
                'cancelled_at' => null,
                'notes' => fake()->optional()->sentence(),
            ]);
        }

        foreach ($books->random(15) as $book) {
            Reservation::create([
                'library_id' => $libraryY->id,
                'book_id' => $book->id,
                'user_id' => $membersY->random()->id,
                'status' => fake()->randomElement(['reserved', 'fulfilled', 'cancelled', 'expired']),
                'reserved_at' => fake()->dateTimeBetween('-20 days', 'now'),
                'expires_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
                'fulfilled_at' => null,
                'cancelled_at' => null,
                'notes' => fake()->optional()->sentence(),
            ]);
        }

        foreach ($copiesX->random(min(40, $copiesX->count())) as $copy) {
            ScanLog::create([
                'library_id' => $libraryX->id,
                'book_copy_id' => $copy->id,
                'user_id' => fake()->randomElement([$adminX->id, $staffX->id]),
                'scan_value' => $copy->qr_code,
                'scan_type' => fake()->randomElement(['info', 'loan', 'return', 'inventory']),
                'result' => fake()->randomElement(['success', 'success', 'success', 'error']),
                'device_info' => fake()->randomElement(['Samsung A52', 'Chrome Windows']),
            ]);
        }

        foreach ($copiesY->random(min(40, $copiesY->count())) as $copy) {
            ScanLog::create([
                'library_id' => $libraryY->id,
                'book_copy_id' => $copy->id,
                'user_id' => fake()->randomElement([$adminY->id, $staffY->id]),
                'scan_value' => $copy->qr_code,
                'scan_type' => fake()->randomElement(['info', 'loan', 'return', 'inventory']),
                'result' => fake()->randomElement(['success', 'success', 'success', 'error']),
                'device_info' => fake()->randomElement(['Xiaomi Redmi', 'Firefox Linux']),
            ]);
        }
    }
}
