<?php

use App\Livewire\Manage\Users\UserForm;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
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
        ->patch(route('manage.users.toggle-membership', $member));

    $response->assertRedirect(route('manage.users.index'));
    $response->assertSessionHas('success');

    expect($member->fresh()->is_active)->toBeTrue()
        ->and($member->libraryMemberships()->where('library_id', $library->id)->value('is_active'))->toBe(false);
});

test('super admin can not deactivate the last active super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create([
        'email' => 'last-super@example.test',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($superAdmin)
        ->from(route('manage.users.index'))
        ->patch(route('manage.users.toggle-global-active', $superAdmin));

    $response->assertRedirect(route('manage.users.index'));
    $response->assertSessionHas('error');

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

test('admin membership deactivation does not affect global account or other libraries', function () {
    $libraryA = Library::factory()->create(['name' => 'Library A']);
    $libraryB = Library::factory()->create(['name' => 'Library B']);
    $admin = User::factory()->for($libraryA)->admin()->create();
    $member = User::factory()->for($libraryA)->member()->create(['is_active' => true]);

    $member->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $member->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->withSession(['active_library_id' => $libraryA->id])
        ->patch(route('manage.users.toggle-membership', $member))
        ->assertRedirect();

    expect($member->fresh()->is_active)->toBeTrue()
        ->and($member->libraryMemberships()->where('library_id', $libraryA->id)->value('is_active'))->toBe(false)
        ->and($member->libraryMemberships()->where('library_id', $libraryB->id)->value('is_active'))->toBe(true);
});

test('admin cannot globally block a user account', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create(['is_active' => true]);

    $this
        ->actingAs($admin)
        ->patch(route('manage.users.toggle-global-active', $member))
        ->assertForbidden();

    expect($member->fresh()->is_active)->toBeTrue();
});

test('inactive membership remains visible and can be restored', function () {
    $library = Library::factory()->create(['name' => 'Visible Library']);
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'email' => 'inactive-visible@example.test',
    ]);

    $member->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['is_active' => false]);

    $this
        ->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->get(route('manage.users.index'))
        ->assertOk()
        ->assertSee('inactive-visible@example.test')
        ->assertSee('Neaktyvus')
        ->assertSee('Atkurti narystę');

    $this
        ->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->patch(route('manage.users.toggle-membership', $member))
        ->assertRedirect();

    expect($member->libraryMemberships()->where('library_id', $library->id)->value('is_active'))->toBe(true);
});

test('super admin can globally block account and revoke api tokens without changing memberships', function () {
    $library = Library::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->for($library)->member()->create(['is_active' => true]);
    $member->createToken('android-app');

    $this
        ->actingAs($superAdmin)
        ->patch(route('manage.users.toggle-global-active', $member))
        ->assertRedirect();

    expect($member->fresh()->is_active)->toBeFalse()
        ->and($member->libraryMemberships()->where('library_id', $library->id)->value('is_active'))->toBe(true)
        ->and(PersonalAccessToken::query()->where('tokenable_id', $member->id)->count())->toBe(0);

    $this
        ->actingAs($member->fresh())
        ->get(route('account.dashboard'))
        ->assertForbidden();
});

test('membership deactivation preserves loans and reservations', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create();
    $branch = Branch::factory()->for($library)->create();
    $location = Location::factory()->for($library)->for($branch)->create();
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);
    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('manage.users.toggle-membership', $member))
        ->assertRedirect();

    expect(Loan::query()->whereKey($loan->id)->exists())->toBeTrue()
        ->and(Reservation::query()->whereKey($reservation->id)->exists())->toBeTrue()
        ->and($member->fresh()->is_active)->toBeTrue();
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
        ->set('role', 'narys')
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
    expect($createdUser->role)->toBe('narys');
    expect($createdUser->library_id)->toBe($library->id);
    expect($createdUser->membership_number)->toStartWith('MEM:');
    expect($createdUser->is_active)->toBeTrue();
});

test('admin cannot assign super admin role through livewire form', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Bad Role')
        ->set('email', 'bad-role@example.test')
        ->set('role', 'superadministratorius')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::query()->where('email', 'bad-role@example.test')->exists())->toBeFalse();
});

test('admin cannot change an existing users global role through livewire form', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $member])
        ->set('role', User::ROLE_STAFF)
        ->call('save')
        ->assertHasErrors(['role']);

    expect($member->fresh()->role)->toBe(User::ROLE_MEMBER);
});

test('admin edit form shows readonly account type instead of editable global role select', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create();

    $this
        ->actingAs($admin)
        ->get(route('manage.users.edit', $member))
        ->assertOk()
        ->assertSee('Paskyros tipas')
        ->assertSee('Skaitytojas')
        ->assertSee('Bibliotekos administratorius šio globalaus paskyros tipo nekeičia.')
        ->assertDontSee('wire:model.live="role"', false)
        ->assertDontSee('<select id="role"', false)
        ->assertSee('Pridėti naują vartotoją')
        ->assertSee(route('manage.users.create'), false);
});

test('admin edit form shows readonly library instead of editable library select', function () {
    $library = Library::factory()->create(['name' => 'Readonly Library']);
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create();

    $this
        ->actingAs($admin)
        ->get(route('manage.users.edit', $member))
        ->assertOk()
        ->assertSee('Readonly Library')
        ->assertDontSee('wire:model.live="libraryId"', false)
        ->assertDontSee('<select id="libraryId"', false);
});

test('create form opened from edit route starts empty and does not carry edited user state', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'name' => 'Edited Member',
        'email' => 'edited-member@example.test',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('manage.users.create'))
        ->assertOk()
        ->assertDontSee('Edited Member')
        ->assertDontSee('edited-member@example.test')
        ->assertSee('value="narys"', false);
});

test('users index shows one add user action and no duplicate staff creation action', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    $this
        ->actingAs($admin)
        ->get(route('manage.users.index'))
        ->assertOk()
        ->assertSee('Pridėti vartotoją')
        ->assertDontSee('Sukurti darbuotojo paskyrą')
        ->assertDontSee('create-staff');
});

test('admin create form allows choosing only member or staff account type', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    $this
        ->actingAs($admin)
        ->get(route('manage.users.create'))
        ->assertOk()
        ->assertSee('Paskyros tipas')
        ->assertSee('Skaitytojas')
        ->assertSee('Darbuotojas')
        ->assertSee('value="narys"', false)
        ->assertSee('value="darbuotojas"', false)
        ->assertDontSee('value="administratorius"', false)
        ->assertDontSee('value="superadministratorius"', false);
});

test('admin can create a staff account from the shared create form', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'New Staff')
        ->set('email', 'new-staff@example.test')
        ->set('role', User::ROLE_STAFF)
        ->set('branchId', $branch->id)
        ->set('phone', '+37060000001')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $staff = User::query()->where('email', 'new-staff@example.test')->firstOrFail();

    expect($staff->role)->toBe(User::ROLE_STAFF)
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->membership_number)->toStartWith('MEM:')
        ->and($staff->libraryMemberships()->where('library_id', $library->id)->where('branch_id', $branch->id)->where('is_active', true)->exists())->toBeTrue();
});

test('admin staff creation requires a branch', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Branchless Staff')
        ->set('email', 'branchless-staff@example.test')
        ->set('role', User::ROLE_STAFF)
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['branchId']);

    expect(User::query()->where('email', 'branchless-staff@example.test')->exists())->toBeFalse();
});

test('admin can create a member from the shared create form and hidden branch state is not saved', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'New Member')
        ->set('email', 'new-member@example.test')
        ->set('role', User::ROLE_STAFF)
        ->set('branchId', $branch->id)
        ->set('role', User::ROLE_MEMBER)
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $member = User::query()->where('email', 'new-member@example.test')->firstOrFail();
    $membership = $member->libraryMemberships()->where('library_id', $library->id)->firstOrFail();

    expect($member->role)->toBe(User::ROLE_MEMBER)
        ->and($membership->branch_id)->toBeNull();
});

test('admin staff creation rejects admin and super admin role tampering', function (string $role) {
    $library = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Bad Staff')
        ->set('email', 'bad-staff@example.test')
        ->set('role', $role)
        ->set('branchId', $branch->id)
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::query()->where('email', 'bad-staff@example.test')->exists())->toBeFalse();
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

test('admin staff creation rejects a branch from another library and leaves no partial user', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $foreignBranch = Branch::factory()->for($otherLibrary)->create();
    $admin = User::factory()->for($library)->admin()->create();

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Foreign Branch Staff')
        ->set('email', 'foreign-branch-staff@example.test')
        ->set('role', User::ROLE_STAFF)
        ->set('branchId', $foreignBranch->id)
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['branchId']);

    expect(User::query()->where('email', 'foreign-branch-staff@example.test')->exists())->toBeFalse();
});

test('admin staff creation does not overwrite an existing member account with same email', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create(['email' => 'shared@example.test']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('name', 'Shared Email Staff')
        ->set('email', 'shared@example.test')
        ->set('role', User::ROLE_STAFF)
        ->set('branchId', $branch->id)
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['email']);

    expect($member->fresh()->role)->toBe(User::ROLE_MEMBER)
        ->and(User::query()->where('email', 'shared@example.test')->count())->toBe(1);
});

test('admin can move existing staff between own library branches without changing global role or account activity', function () {
    $library = Library::factory()->create();
    $firstBranch = Branch::factory()->for($library)->create();
    $secondBranch = Branch::factory()->for($library)->create();
    $admin = User::factory()->for($library)->admin()->create();
    $staff = User::factory()->staff()->create(['is_active' => true]);
    UserManagement::syncLibraryMembership($staff, $library->id, $firstBranch->id);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('branchId', $secondBranch->id)
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $membership = $staff->libraryMemberships()->where('library_id', $library->id)->firstOrFail();

    expect($staff->fresh()->role)->toBe(User::ROLE_STAFF)
        ->and($staff->fresh()->is_active)->toBeTrue()
        ->and($membership->fresh()->branch_id)->toBe($secondBranch->id);
});

test('admin cannot change an existing users library through livewire payload', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $otherBranch = Branch::factory()->for($otherLibrary)->create();
    $admin = User::factory()->for($library)->admin()->create();
    $staff = User::factory()->staff()->create();
    UserManagement::syncLibraryMembership($staff, $library->id, $branch->id);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('libraryId', $otherLibrary->id)
        ->set('branchId', $otherBranch->id)
        ->call('save')
        ->assertHasErrors(['libraryId'])
        ->assertSessionMissing('success');

    $membership = $staff->libraryMemberships()->where('user_id', $staff->id)->firstOrFail();

    expect($membership->library_id)->toBe($library->id)
        ->and($membership->branch_id)->toBe($branch->id);
});

test('admin edit form persists allowed user fields to users table', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'name' => 'Old Name',
        'email' => 'old-name@example.test',
        'phone' => '111',
    ]);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $member])
        ->set('name', 'New Name')
        ->set('email', 'new-name@example.test')
        ->set('phone', '222')
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    expect($member->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new-name@example.test')
        ->phone->toBe('222');

    $this->actingAs($admin)
        ->get(route('manage.users.index'))
        ->assertOk()
        ->assertSee('New Name')
        ->assertDontSee('Old Name');
});

test('admin edit form persists user and membership changes in one submit', function () {
    $library = Library::factory()->create();
    $firstBranch = Branch::factory()->for($library)->create(['name' => 'Old Branch']);
    $secondBranch = Branch::factory()->for($library)->create(['name' => 'New Branch']);
    $admin = User::factory()->for($library)->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Old Staff']);
    UserManagement::syncLibraryMembership($staff, $library->id, $firstBranch->id);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('name', 'New Staff')
        ->set('branchId', $secondBranch->id)
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $membership = $staff->libraryMemberships()->where('library_id', $library->id)->firstOrFail();
    $auditLog = AuditLog::query()->where('action', 'user_updated')->latest('id')->firstOrFail();

    expect($staff->fresh()->name)->toBe('New Staff')
        ->and($membership->fresh()->branch_id)->toBe($secondBranch->id)
        ->and($auditLog->metadata['changed_fields'])->toContain('name', 'membership.branch_id')
        ->and($auditLog->metadata['changes'])->toContain([
            'field' => 'name',
            'label' => 'Vardas',
            'from' => 'Old Staff',
            'to' => 'New Staff',
        ])
        ->and($auditLog->metadata['changes'])->toContain([
            'field' => 'membership.branch_id',
            'label' => 'Narystės filialas',
            'from' => 'Old Branch',
            'to' => 'New Branch',
        ]);
});

test('super admin can transfer existing staff membership to another library and branch', function () {
    $sourceLibrary = Library::factory()->create(['name' => 'Source Library']);
    $targetLibrary = Library::factory()->create(['name' => 'Target Library']);
    $sourceBranch = Branch::factory()->for($sourceLibrary)->create(['name' => 'Source Branch']);
    $targetBranch = Branch::factory()->for($targetLibrary)->create(['name' => 'Target Branch']);
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Transfer Staff']);
    $membership = UserManagement::syncLibraryMembership($staff, $sourceLibrary->id, $sourceBranch->id);
    $staff->createToken('android-app');

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->assertSet('membershipId', $membership->id)
        ->assertSet('sourceLibraryId', $sourceLibrary->id)
        ->set('libraryId', $targetLibrary->id)
        ->assertSet('branchId', null)
        ->set('branchId', $targetBranch->id)
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    $membership = $membership->fresh();
    $auditLog = AuditLog::query()->where('action', 'user_updated')->latest('id')->firstOrFail();

    expect($membership->library_id)->toBe($targetLibrary->id)
        ->and($membership->branch_id)->toBe($targetBranch->id)
        ->and($staff->libraryMemberships()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->where('tokenable_id', $staff->id)->count())->toBe(0)
        ->and($auditLog->metadata['changed_fields'])->toContain('membership.library_id', 'membership.branch_id')
        ->and($auditLog->metadata['changes'])->toContain([
            'field' => 'membership.library_id',
            'label' => 'Narystės biblioteka',
            'from' => 'Source Library',
            'to' => 'Target Library',
        ])
        ->and($auditLog->metadata['changes'])->toContain([
            'field' => 'membership.branch_id',
            'label' => 'Narystės filialas',
            'from' => 'Source Branch',
            'to' => 'Target Branch',
        ]);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $staff->fresh()])
        ->assertSet('membershipId', $membership->id)
        ->assertSet('libraryId', $targetLibrary->id)
        ->assertSet('branchId', $targetBranch->id);
});

test('super admin membership transfer rejects target library conflicts without changing source membership', function () {
    $sourceLibrary = Library::factory()->create();
    $targetLibrary = Library::factory()->create();
    $sourceBranch = Branch::factory()->for($sourceLibrary)->create();
    $targetBranch = Branch::factory()->for($targetLibrary)->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create();
    $sourceMembership = UserManagement::syncLibraryMembership($staff, $sourceLibrary->id, $sourceBranch->id);
    $staff->libraryMemberships()->create([
        'library_id' => $targetLibrary->id,
        'branch_id' => $targetBranch->id,
        'membership_number' => $staff->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('libraryId', $targetLibrary->id)
        ->assertSet('branchId', null)
        ->set('branchId', $targetBranch->id)
        ->call('save')
        ->assertHasErrors(['libraryId'])
        ->assertSessionMissing('success');

    expect($sourceMembership->fresh()->library_id)->toBe($sourceLibrary->id)
        ->and($sourceMembership->fresh()->branch_id)->toBe($sourceBranch->id)
        ->and($staff->libraryMemberships()->count())->toBe(2);
});

test('super admin membership transfer rejects foreign branch and leaves no partial update', function () {
    $sourceLibrary = Library::factory()->create();
    $targetLibrary = Library::factory()->create();
    $foreignLibrary = Library::factory()->create();
    $sourceBranch = Branch::factory()->for($sourceLibrary)->create();
    $foreignBranch = Branch::factory()->for($foreignLibrary)->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create(['name' => 'No Partial']);
    $membership = UserManagement::syncLibraryMembership($staff, $sourceLibrary->id, $sourceBranch->id);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('name', 'Should Roll Back')
        ->set('libraryId', $targetLibrary->id)
        ->assertSet('branchId', null)
        ->set('branchId', $foreignBranch->id)
        ->call('save')
        ->assertHasErrors(['branchId'])
        ->assertSessionMissing('success');

    expect($staff->fresh()->name)->toBe('No Partial')
        ->and($membership->fresh()->library_id)->toBe($sourceLibrary->id)
        ->and($membership->fresh()->branch_id)->toBe($sourceBranch->id)
        ->and(AuditLog::query()->where('action', 'user_updated')->exists())->toBeFalse();
});

test('super admin membership transfer rejects source library loan history without partial update', function () {
    $sourceLibrary = Library::factory()->create();
    $targetLibrary = Library::factory()->create();
    $sourceBranch = Branch::factory()->for($sourceLibrary)->create();
    $targetBranch = Branch::factory()->for($targetLibrary)->create();
    $sourceLocation = Location::factory()->for($sourceLibrary)->for($sourceBranch)->create();
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $sourceLibrary->id,
        'branch_id' => $sourceBranch->id,
        'location_id' => $sourceLocation->id,
        'book_id' => $book->id,
    ]);
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create();
    $membership = UserManagement::syncLibraryMembership($staff, $sourceLibrary->id, $sourceBranch->id);

    Loan::factory()->create([
        'library_id' => $sourceLibrary->id,
        'book_copy_id' => $copy->id,
        'user_id' => $staff->id,
        'returned_at' => now(),
        'status' => 'grąžinta',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('libraryId', $targetLibrary->id)
        ->set('branchId', $targetBranch->id)
        ->call('save')
        ->assertHasErrors(['libraryId'])
        ->assertSessionMissing('success');

    expect($membership->fresh()->library_id)->toBe($sourceLibrary->id)
        ->and($membership->fresh()->branch_id)->toBe($sourceBranch->id);
});

test('admin edit form no-op does not update audit log or show false success', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->for($library)->admin()->create();
    $member = User::factory()->for($library)->member()->create([
        'name' => 'No Op Member',
        'email' => 'noop@example.test',
    ]);
    $auditCountBefore = AuditLog::query()->count();

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $member])
        ->call('save')
        ->assertRedirect(route('manage.users.edit', $member))
        ->assertSessionHas('info', 'Nebuvo atlikta jokių pakeitimų.')
        ->assertSessionMissing('success');

    expect(AuditLog::query()->count())->toBe($auditCountBefore);
});

test('admin edit form rejects invalid membership branch without partial user update or success audit', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $foreignBranch = Branch::factory()->for($otherLibrary)->create();
    $admin = User::factory()->for($library)->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Stable Staff']);
    UserManagement::syncLibraryMembership($staff, $library->id, $branch->id);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('name', 'Should Not Persist')
        ->set('branchId', $foreignBranch->id)
        ->call('save')
        ->assertHasErrors(['branchId'])
        ->assertSessionMissing('success');

    expect($staff->fresh()->name)->toBe('Stable Staff')
        ->and($staff->libraryMemberships()->where('library_id', $library->id)->value('branch_id'))->toBe($branch->id)
        ->and(AuditLog::query()->where('action', 'user_updated')->exists())->toBeFalse();
});

test('admin cannot assign existing staff to a branch from another library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $branch = Branch::factory()->for($library)->create();
    $foreignBranch = Branch::factory()->for($otherLibrary)->create();
    $admin = User::factory()->for($library)->admin()->create();
    $staff = User::factory()->staff()->create(['is_active' => true]);
    UserManagement::syncLibraryMembership($staff, $library->id, $branch->id);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['managedUser' => $staff])
        ->set('branchId', $foreignBranch->id)
        ->call('save')
        ->assertHasErrors(['branchId']);

    expect($staff->fresh()->role)->toBe(User::ROLE_STAFF)
        ->and($staff->libraryMemberships()->where('library_id', $library->id)->value('branch_id'))->toBe($branch->id);
});

test('staff cannot open the user creation route', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->for($library)->staff()->create();

    $this
        ->actingAs($staff)
        ->get(route('manage.users.index'))
        ->assertOk()
        ->assertDontSee('Pridėti vartotoją');

    $this
        ->actingAs($staff)
        ->get(route('manage.users.create'))
        ->assertForbidden();
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
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => 'gera',
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
        ->set('role', 'darbuotojas')
        ->call('save')
        ->assertHasErrors(['role']);

    expect($admin->fresh()->role)->toBe('administratorius');
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
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => 'gera',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'aktyvi',
    ]);

    Reservation::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
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
    $response->assertSee('Aktyviai išduotos knygos');
    $response->assertSee('Laukiančios rezervacijos');
    $response->assertSee('Testine knyga');
});

test('admin sees only active library member loans and reservations on user show page', function () {
    $libraryA = Library::factory()->create(['name' => 'Library A']);
    $libraryB = Library::factory()->create(['name' => 'Library B']);

    $admin = adminInLibrary($libraryA);
    $member = memberInLibrary($libraryA, [
        'membership_number' => 'MULTI-MEM-001',
    ]);

    $member->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $member->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $branchA = Branch::factory()->for($libraryA)->create();
    $locationA = Location::factory()->for($libraryA)->for($branchA)->create();
    $branchB = Branch::factory()->for($libraryB)->create();
    $locationB = Location::factory()->for($libraryB)->for($branchB)->create();

    $bookA = Book::factory()->create(['title' => 'Visible library A book']);
    $bookB = Book::factory()->create(['title' => 'Hidden library B book']);

    $copyA = BookCopy::factory()->create([
        'library_id' => $libraryA->id,
        'book_id' => $bookA->id,
        'branch_id' => $branchA->id,
        'location_id' => $locationA->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $copyB = BookCopy::factory()->create([
        'library_id' => $libraryB->id,
        'book_id' => $bookB->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $libraryA->id,
        'book_copy_id' => $copyA->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'aktyvi',
    ]);

    Loan::factory()->create([
        'library_id' => $libraryB->id,
        'book_copy_id' => $copyB->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'aktyvi',
    ]);

    Reservation::factory()->create([
        'library_id' => $libraryB->id,
        'book_id' => $bookB->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['active_library_id' => $libraryA->id])
        ->get(route('manage.users.show', $member));

    $response->assertOk();
    $response->assertSee('Visible library A book');
    $response->assertDontSee('Hidden library B book');
    $response->assertSee('Aktyviai išduotos knygos');
    $response->assertSee('Visos išduotos knygos');
    $response->assertSee('Laukiančios rezervacijos');
    $response->assertDontSee('Library B');
});
