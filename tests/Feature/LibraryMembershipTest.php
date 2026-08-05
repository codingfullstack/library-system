<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a member to belong to more than one public library as member only', function () {
    $firstLibrary = Library::factory()->create();
    $secondLibrary = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $firstLibrary->id]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $secondLibrary->id,
        'user_id' => $user->id,
        'membership_number' => 'SECOND-MEM-001',
    ]);

    expect($user->fresh()->belongsToLibrary($firstLibrary->id))->toBeTrue()
        ->and($user->fresh()->belongsToLibrary($secondLibrary->id))->toBeTrue()
        ->and($user->fresh()->libraryRole($secondLibrary->id))->toBe('narys')
        ->and($user->fresh()->effectiveRole($secondLibrary->id))->toBe('narys');
});

it('lets staff manage visible member users through their assigned branch activity', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => null]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $library->id,
        'user_id' => $member->id,
        'membership_number' => 'MEMBERSHIP-001',
    ]);
    UserManagement::syncLibraryMembership($staff, $library->id, $branch->id);
    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    expect(UserManagement::canManageUser($staff, $member->fresh()))->toBeTrue();
});

it('does not allow member memberships to grant staff dashboard access', function () {
    $memberLibrary = Library::factory()->create();
    $secondLibrary = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $memberLibrary->id]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $secondLibrary->id,
        'user_id' => $user->id,
        'membership_number' => 'SECOND-MEM-003',
    ]);

    $this->actingAs($user)
        ->withSession(['active_library_id' => $secondLibrary->id])
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('lets an admin add and deactivate a membership in the active library', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => null]);

    $this->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->post(route('manage.users.memberships.store', $member), [
        ])
        ->assertRedirect();

    $membership = LibraryMembership::query()
        ->where('library_id', $library->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    expect($membership->is_active)->toBeTrue()
        ->and($membership->membership_number)->not->toBeNull();

    $this->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->patch(route('manage.users.memberships.toggle', [$member, $membership]))
        ->assertRedirect();

    expect($membership->fresh()->is_active)->toBeFalse();
});

it('lets a member join only public libraries', function () {
    $publicLibrary = Library::factory()->create(['is_public' => true]);
    $privateLibrary = Library::factory()->create(['is_public' => false]);
    $member = User::factory()->member()->create(['library_id' => null]);

    $this->actingAs($member)
        ->post(route('libraries.join', $publicLibrary))
        ->assertRedirect(route('account.dashboard'));

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $publicLibrary->id,
        'user_id' => $member->id,
        'is_active' => true,
    ]);

    $this->actingAs($member)
        ->post(route('libraries.join', $privateLibrary))
        ->assertNotFound();

    $this->assertDatabaseMissing('library_memberships', [
        'library_id' => $privateLibrary->id,
        'user_id' => $member->id,
    ]);
});

it('shows none active and inactive membership states in the public libraries list', function () {
    $noneLibrary = Library::factory()->create(['is_public' => true, 'name' => 'Biblioteka be narystės']);
    $activeLibrary = Library::factory()->create(['is_public' => true, 'name' => 'Aktyvi biblioteka']);
    $inactiveLibrary = Library::factory()->create(['is_public' => true, 'name' => 'Neaktyvi biblioteka']);
    $member = User::factory()->member()->create(['library_id' => $activeLibrary->id]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $inactiveLibrary->id,
        'user_id' => $member->id,
        'membership_number' => 'INACTIVE-MEM-001',
        'is_active' => false,
    ]);

    $response = $this->actingAs($member)
        ->get(route('public.libraries.index'));

    $response
        ->assertOk()
        ->assertSee($noneLibrary->name)
        ->assertSee($activeLibrary->name)
        ->assertSee($inactiveLibrary->name)
        ->assertSee('Prisijungti prie bibliotekos')
        ->assertSee('Jau prisijungta')
        ->assertSee('Narystė neaktyvi')
        ->assertSee('Jūsų narystė šioje bibliotekoje yra deaktyvuota. Dėl atkūrimo kreipkitės į bibliotekos administratorių.');
});

it('does not show an active join action for inactive memberships', function () {
    $library = Library::factory()->create(['is_public' => true, 'name' => 'Užblokuota biblioteka']);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    $member->libraryMemberships()->where('library_id', $library->id)->update(['is_active' => false]);

    $this->actingAs($member)
        ->get(route('public.libraries.index'))
        ->assertOk()
        ->assertSee('Narystė neaktyvi')
        ->assertDontSee('action="'.route('libraries.join', $library).'"', false);
});

it('rejects a direct web join request for an inactive membership without reactivating it', function () {
    $library = Library::factory()->create(['is_public' => true]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $membership = $member->libraryMemberships()->where('library_id', $library->id)->firstOrFail();

    $membership->update(['is_active' => false]);

    $this->actingAs($member)
        ->post(route('libraries.join', $library))
        ->assertForbidden()
        ->assertSee('Jūsų narystė šioje bibliotekoje yra deaktyvuota. Dėl atkūrimo kreipkitės į bibliotekos administratorių.');

    expect($membership->fresh()->is_active)->toBeFalse()
        ->and(LibraryMembership::query()
            ->where('library_id', $library->id)
            ->where('user_id', $member->id)
            ->count())->toBe(1);
});

it('returns membership state and blocks inactive membership rejoin through the api', function () {
    $activeLibrary = Library::factory()->create(['is_public' => true]);
    $inactiveLibrary = Library::factory()->create(['is_public' => true, 'name' => 'API neaktyvi biblioteka']);
    $joinableLibrary = Library::factory()->create(['is_public' => true, 'name' => 'API nauja biblioteka']);
    $member = User::factory()->member()->create(['library_id' => $activeLibrary->id]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $inactiveLibrary->id,
        'user_id' => $member->id,
        'membership_number' => 'INACTIVE-API-001',
        'is_active' => false,
    ]);

    $this->actingAs($member)
        ->getJson('/api/auth/libraries/public')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $activeLibrary->id,
            'membership_status' => 'active',
            'can_join' => false,
        ])
        ->assertJsonFragment([
            'id' => $inactiveLibrary->id,
            'membership_status' => 'inactive',
            'can_join' => false,
        ])
        ->assertJsonFragment([
            'id' => $joinableLibrary->id,
            'membership_status' => 'none',
            'can_join' => true,
        ]);

    $this->actingAs($member)
        ->postJson('/api/auth/libraries/'.$inactiveLibrary->id.'/join')
        ->assertForbidden()
        ->assertJsonPath('message', 'Jūsų narystė šioje bibliotekoje yra deaktyvuota. Dėl atkūrimo kreipkitės į bibliotekos administratorių.');

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $inactiveLibrary->id,
        'user_id' => $member->id,
        'is_active' => false,
    ]);

    expect(LibraryMembership::query()
        ->where('library_id', $inactiveLibrary->id)
        ->where('user_id', $member->id)
        ->count())->toBe(1);
});

it('keeps public library join idempotent without creating duplicate memberships', function () {
    $library = Library::factory()->create(['is_public' => true]);
    $member = User::factory()->member()->create(['library_id' => null]);

    $this->actingAs($member)
        ->post(route('libraries.join', $library))
        ->assertRedirect(route('account.dashboard'));

    $this->actingAs($member)
        ->post(route('libraries.join', $library))
        ->assertRedirect(route('account.dashboard'));

    expect(LibraryMembership::query()
        ->where('library_id', $library->id)
        ->where('user_id', $member->id)
        ->count())->toBe(1);
});

it('allows only the owning library administrator to restore an inactive membership', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $adminA = User::factory()->admin()->create(['library_id' => $libraryA->id]);
    $adminB = User::factory()->admin()->create(['library_id' => $libraryB->id]);
    $member = User::factory()->member()->create(['library_id' => $libraryA->id]);
    $membership = $member->libraryMemberships()->where('library_id', $libraryA->id)->firstOrFail();

    $membership->update(['is_active' => false]);

    $this->actingAs($member)
        ->patch(route('manage.users.memberships.toggle', [$member, $membership]))
        ->assertForbidden();

    $this->actingAs($adminB)
        ->withSession(['active_library_id' => $libraryB->id])
        ->patch(route('manage.users.memberships.toggle', [$member, $membership]))
        ->assertForbidden();

    expect($membership->fresh()->is_active)->toBeFalse();

    $this->actingAs($adminA)
        ->withSession(['active_library_id' => $libraryA->id])
        ->patch(route('manage.users.memberships.toggle', [$member, $membership]))
        ->assertRedirect();

    expect($membership->fresh()->is_active)->toBeTrue();
});

it('denies protected web and api access to an inactive membership while another active membership still works', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $libraryA->id]);

    $member->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $member->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $member->libraryMemberships()->where('library_id', $libraryA->id)->update(['is_active' => false]);

    $this->actingAs($member)
        ->withSession(['active_library_id' => $libraryA->id])
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSessionHas('active_library_id', $libraryB->id);

    $this->actingAs($member)
        ->withHeader('X-Library-Id', (string) $libraryA->id)
        ->getJson('/api/auth/me')
        ->assertForbidden();

    $this->actingAs($member)
        ->withHeader('X-Library-Id', (string) $libraryB->id)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.library_id', $libraryB->id);
});

it('lists public libraries and lets a member join one through the api', function () {
    $joinedLibrary = Library::factory()->create(['is_public' => true]);
    $publicLibrary = Library::factory()->create(['is_public' => true, 'name' => 'Vieša biblioteka']);
    $privateLibrary = Library::factory()->create(['is_public' => false]);
    $member = User::factory()->member()->create(['library_id' => $joinedLibrary->id]);

    $this->actingAs($member)
        ->getJson('/api/auth/libraries/public')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $joinedLibrary->id,
            'is_member' => true,
        ])
        ->assertJsonFragment([
            'id' => $publicLibrary->id,
            'name' => 'Vieša biblioteka',
            'is_member' => false,
        ])
        ->assertJsonMissing([
            'id' => $privateLibrary->id,
        ]);

    $this->actingAs($member)
        ->postJson('/api/auth/libraries/'.$publicLibrary->id.'/join')
        ->assertOk()
        ->assertJsonPath('library.id', $publicLibrary->id)
        ->assertJsonPath('library.is_member', true);

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $publicLibrary->id,
        'user_id' => $member->id,
        'is_active' => true,
    ]);
});

it('does not let a member join a private library through the api', function () {
    $memberLibrary = Library::factory()->create();
    $privateLibrary = Library::factory()->create(['is_public' => false]);
    $member = User::factory()->member()->create(['library_id' => $memberLibrary->id]);

    $this->actingAs($member)
        ->postJson('/api/auth/libraries/'.$privateLibrary->id.'/join')
        ->assertNotFound();

    $this->assertDatabaseMissing('library_memberships', [
        'library_id' => $privateLibrary->id,
        'user_id' => $member->id,
    ]);
});

it('lets staff add an existing member to a private library by scanned membership number', function () {
    $privateLibrary = Library::factory()->create(['is_public' => false]);
    $otherLibrary = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $privateLibrary->id]);
    $member = User::factory()->member()->create([
        'library_id' => $otherLibrary->id,
        'membership_number' => 'MEM:01HZX7J2DQBQ6VYVR9X2W9TEST',
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/members/by-membership/'.urlencode($member->membership_number))
        ->assertOk()
        ->assertJsonPath('member.name', $member->name)
        ->assertJsonPath('member.email', $member->email)
        ->assertJsonPath('member.phone', $member->phone)
        ->assertJsonPath('already_member', false);

    $this->actingAs($staff)
        ->postJson('/api/auth/memberships/scan', [
            'membership_number' => $member->membership_number,
        ])
        ->assertOk()
        ->assertJsonPath('member.name', $member->name);

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $privateLibrary->id,
        'user_id' => $member->id,
        'membership_number' => $member->membership_number,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'library_id' => $privateLibrary->id,
        'action' => 'user_membership_added_by_scan',
        'auditable_type' => (new User)->getMorphClass(),
        'auditable_id' => $member->id,
    ]);

    expect($member->notifications()
        ->where('type', 'library_membership_added')
        ->exists())->toBeTrue();
});

it('shows a scanned member is already in the active library', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create([
        'library_id' => $library->id,
        'membership_number' => 'MEM:01HZX7J2DQBQ6VYVR9X2W9DONE',
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/members/by-membership/'.urlencode($member->membership_number))
        ->assertOk()
        ->assertJsonPath('already_member', true);
});

it('adds scanned members to the staff work library instead of the session library context', function () {
    $workLibrary = Library::factory()->create(['is_public' => false]);
    $wrongLibrary = Library::factory()->create(['is_public' => false]);
    $memberLibrary = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $workLibrary->id]);
    $member = User::factory()->member()->create([
        'library_id' => $memberLibrary->id,
        'membership_number' => 'MEM:01HZX7J2DQBQ6VYVR9X2W9WORK',
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $wrongLibrary->id])
        ->postJson('/api/auth/memberships/scan', [
            'membership_number' => $member->membership_number,
        ])
        ->assertOk();

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $workLibrary->id,
        'user_id' => $member->id,
        'is_active' => true,
    ]);

    $this->assertDatabaseMissing('library_memberships', [
        'library_id' => $wrongLibrary->id,
        'user_id' => $member->id,
    ]);
});

it('lets a super admin choose a library when adding a scanned member through the api', function () {
    $library = Library::factory()->create(['is_public' => false]);
    $otherLibrary = Library::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->member()->create([
        'library_id' => $otherLibrary->id,
        'membership_number' => 'MEM:01HZX7J2DQBQ6VYVR9X2W9ROOT',
    ]);

    $this->actingAs($superAdmin)
        ->getJson('/api/auth/members/by-membership/'.urlencode($member->membership_number))
        ->assertOk()
        ->assertJsonPath('member.name', $member->name)
        ->assertJsonFragment(['id' => $library->id, 'name' => $library->name]);

    $this->actingAs($superAdmin)
        ->postJson('/api/auth/memberships/scan', [
            'membership_number' => $member->membership_number,
            'library_id' => $library->id,
        ])
        ->assertOk()
        ->assertJsonPath('member.name', $member->name);

    $this->assertDatabaseHas('library_memberships', [
        'library_id' => $library->id,
        'user_id' => $member->id,
        'is_active' => true,
    ]);
});

it('shows public libraries navigation to members', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    $this->actingAs($member)
        ->withSession(['active_library_id' => $library->id])
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Viešosios bibliotekos');
});
