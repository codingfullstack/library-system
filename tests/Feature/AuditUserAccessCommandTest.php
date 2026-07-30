<?php

use App\Models\Branch;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes when user access invariants are clean', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);

    memberInLibrary($library);
    adminInLibrary($library);
    staffInBranch($library, $branch);
    superAdmin();

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('User access audit passed.')
        ->assertExitCode(0);
});

it('fails and reports concrete ids when access invariants are violated', function () {
    $library = Library::factory()->create();
    $staff = staffWithoutBranch($library, ['email' => 'branchless.staff@example.com']);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('active_staff_without_branch')
        ->expectsOutputToContain('User access audit found violations.')
        ->assertExitCode(1);

    expect($staff->fresh()->assignedBranchId($library->id))->toBeNull();
});

it('fails when an active admin has no active membership', function () {
    User::factory()->admin()->create(['email' => 'admin.no.membership@example.com']);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('active_admin_without_membership')
        ->assertExitCode(1);
});

it('fails when a member has branch scope', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $member = memberInLibrary($library, ['email' => 'member.branch@example.com']);
    $member->libraryMemberships()->where('library_id', $library->id)->update(['branch_id' => $branch->id]);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('active_member_with_branch_scope')
        ->assertExitCode(1);
});

it('fails when an active branch has no staff', function () {
    $library = Library::factory()->create();
    Branch::factory()->create(['library_id' => $library->id, 'code' => 'NO-STAFF']);
    adminInLibrary($library);
    memberInLibrary($library);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('active_branch_without_staff')
        ->assertExitCode(1);
});

it('fails when a staff membership points to another library branch', function () {
    $library = Library::factory()->create();
    $foreignLibrary = Library::factory()->create();
    $foreignBranch = Branch::factory()->create(['library_id' => $foreignLibrary->id]);
    staffWithForeignBranch($library, $foreignBranch, ['email' => 'foreign.branch.staff@example.com']);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('staff_branch_library_mismatch')
        ->assertExitCode(1);
});

it('keeps inactive users and inactive memberships without effective roles', function () {
    $library = Library::factory()->create();
    $inactiveMember = memberInLibrary($library, [
        'email' => 'inactive.member@example.com',
        'is_active' => false,
    ]);
    $inactiveMember->libraryMemberships()->where('library_id', $library->id)->update(['is_active' => false]);

    $this->artisan('system:audit-user-access')
        ->expectsOutputToContain('inactive_membership_with_effective_role')
        ->expectsOutputToContain('inactive_user_with_effective_role')
        ->assertExitCode(0);

    expect($inactiveMember->fresh()->effectiveRole($library->id))->toBeNull();
});
