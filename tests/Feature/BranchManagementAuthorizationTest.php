<?php

use App\Models\Branch;
use App\Models\Library;
use App\Models\User;

it('blocks staff from branch management pages', function () {
    $library = Library::factory()->create();
    Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->get(route('manage.branches.index'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('manage.branches.create'))
        ->assertForbidden();
});

it('allows administrators to manage branches', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);

    $this->actingAs($admin)
        ->get(route('manage.branches.index'))
        ->assertOk();
});

it('blocks staff from importing and exporting branches', function () {
    $library = Library::factory()->create();
    Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->get(route('manage.imports.show', 'branches'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('exports.list', ['resource' => 'branches']))
        ->assertForbidden();
});
