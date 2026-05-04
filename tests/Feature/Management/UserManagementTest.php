<?php

use App\Livewire\Manage\Users\UserForm;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin sees only manageable users from own library', function () {
    $libraryA = Library::factory()->create(['name' => 'Library A', 'code' => 'LIBA']);
    $libraryB = Library::factory()->create(['name' => 'Library B', 'code' => 'LIBB']);

    $admin = User::factory()->for($libraryA)->admin()->create([
        'name' => 'Admin A',
        'email' => 'admin-a@example.test',
    ]);

    $sameLibraryStaff = User::factory()->for($libraryA)->staff()->create([
        'email' => 'staff-a@example.test',
    ]);

    $sameLibraryMember = User::factory()->for($libraryA)->member()->create([
        'email' => 'member-a@example.test',
    ]);

    $otherLibraryAdmin = User::factory()->for($libraryB)->admin()->create([
        'email' => 'admin-b@example.test',
    ]);

    $superAdmin = User::factory()->superAdmin()->create([
        'email' => 'super@example.test',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('manage.users.index'));

    $response->assertOk();
    $response->assertSee('admin-a@example.test');
    $response->assertSee('staff-a@example.test');
    $response->assertSee('member-a@example.test');
    $response->assertDontSee('admin-b@example.test');
    $response->assertDontSee('super@example.test');
});

test('staff cannot open admin user from same library', function () {
    $library = Library::factory()->create();

    $staff = User::factory()->for($library)->staff()->create();
    $admin = User::factory()->for($library)->admin()->create();

    $this->actingAs($staff)
        ->get(route('manage.users.show', $admin))
        ->assertNotFound();
});

test('admin can toggle member active status from index', function () {
    $library = Library::factory()->create();

    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('manage.users.index'))
        ->patch(route('manage.users.toggle-active', $member));

    $response->assertRedirect(route('manage.users.index'));
    $response->assertSessionHas('success');

    expect($member->fresh()->is_active)->toBeFalse();
});

test('super admin can not deactivate the last active super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create([
        'email' => 'last-super@example.test',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($superAdmin)
        ->from(route('manage.users.index'))
        ->patch(route('manage.users.toggle-active', $superAdmin));

    $response->assertRedirect(route('manage.users.index'));
    $response->assertSessionHas('error');

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

test('super admin can create member with generated membership number through livewire form', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create([
        'code' => 'KAL',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class)
        ->set('name', 'Test Member')
        ->set('email', 'member-created@example.test')
        ->set('role', 'member')
        ->set('libraryId', $library->id)
        ->set('phone', '37060000000')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('isActive', true)
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $createdUser = User::query()
        ->where('email', 'member-created@example.test')
        ->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser->role)->toBe('member');
    expect($createdUser->library_id)->toBe($library->id);
    expect($createdUser->membership_number)->toStartWith('KAL-MEM-');
    expect($createdUser->is_active)->toBeTrue();
});

test('admin cannot assign super admin role through livewire form', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Bad Role')
        ->set('email', 'bad-role@example.test')
        ->set('role', 'super_admin')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::query()->where('email', 'bad-role@example.test')->exists())->toBeFalse();
});

test('super admin can open manageable user show page with summary', function () {
    $library = Library::factory()->create(['name' => 'Kaltinenu biblioteka', 'code' => 'KAL']);
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->for($library)->member()->create([
        'name' => 'Testinis Narys',
        'email' => 'show-member@example.test',
        'membership_number' => 'KAL-MEM-001',
    ]);

    $response = $this
        ->actingAs($superAdmin)
        ->get(route('manage.users.show', $member));

    $response->assertOk();
    $response->assertSee('Testinis Narys');
    $response->assertSee('Kaltinenu biblioteka');
    $response->assertSee('KAL-MEM-001');
});

test('admin cannot open user from another library show page', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();

    $admin = User::factory()->for($libraryA)->admin()->create();
    $otherLibraryMember = User::factory()->for($libraryB)->member()->create();

    $this->actingAs($admin)
        ->get(route('manage.users.show', $otherLibraryMember))
        ->assertNotFound();
});

test('user with history can not be deleted', function () {
    $library = Library::factory()->create(['code' => 'KAL']);
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'membership_number' => 'KAL-MEM-001',
    ]);

    $branch = Branch::factory()->for($library)->create();
    $location = Location::factory()->for($library)->for($branch)->create();
    $book = Book::factory()->create();
    $copy = BookCopy::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-DELETE-001',
        'qr_code' => 'QR-DELETE-001',
        'barcode' => '12345678901',
        'status' => 'available',
        'condition_status' => 'good',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('manage.users.index'))
        ->delete(route('manage.users.destroy', $member));

    $response->assertRedirect(route('manage.users.index'));
    $response->assertSessionHas('error');

    expect(User::query()->whereKey($member->id)->exists())->toBeTrue();
});

test('admin can not change own role through livewire form', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $admin])
        ->set('role', 'staff')
        ->call('save')
        ->assertHasErrors(['role']);

    expect($admin->fresh()->role)->toBe('admin');
});

test('super admin editing own account can not deactivate self through livewire form', function () {
    $superAdmin = User::factory()->superAdmin()->create([
        'is_active' => true,
    ]);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $superAdmin])
        ->set('isActive', false)
        ->call('save')
        ->assertHasErrors(['isActive']);

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

test('member reservation and loan counts are visible on show page', function () {
    $library = Library::factory()->create(['name' => 'Test Library', 'code' => 'TST']);
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->for($library)->member()->create([
        'membership_number' => 'TST-MEM-001',
    ]);

    $branch = Branch::factory()->for($library)->create();
    $location = Location::factory()->for($library)->for($branch)->create();
    $book = Book::factory()->create(['title' => 'Testine knyga']);
    $copy = BookCopy::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-SHOW-001',
        'qr_code' => 'QR-SHOW-001',
        'barcode' => '12345678902',
        'status' => 'loaned',
        'condition_status' => 'good',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'active',
    ]);

    Reservation::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
        'expires_at' => now()->addDay(),
        'notes' => null,
    ]);

    $response = $this
        ->actingAs($superAdmin)
        ->get(route('manage.users.show', $member));

    $response->assertOk();
    $response->assertSee('Aktyviai isduotos knygos');
    $response->assertSee('Laukiancios rezervacijos');
    $response->assertSee('Testine knyga');
});
