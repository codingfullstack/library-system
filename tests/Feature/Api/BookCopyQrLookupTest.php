<?php

use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a validation message for a qr code outside this system standard', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr?qr_code=' . urlencode('http://en.wikipedia.org'))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Neatpažintas QR kodas. Nuskenuokite šios sistemos sugeneruotą knygos QR kodą.',
        ]);
});

it('returns a validation message when an old path lookup receives a url qr code', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr/' . urlencode('http://en.wikipedia.org'))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Neatpažintas QR kodas. Nuskenuokite šios sistemos sugeneruotą knygos QR kodą.',
        ]);
});

it('can look up a book copy by qr code through a query parameter', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'qr_code' => 'QR-LOOKUP-001',
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr?qr_code=' . urlencode($copy->qr_code))
        ->assertOk()
        ->assertJsonPath('id', $copy->id)
        ->assertJsonPath('qr_code', 'QR-LOOKUP-001');
});

it('can look up a book copy by a prefixed system qr code', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'qr_code' => 'PRES-QR-003600',
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr?qr_code='.urlencode($copy->qr_code))
        ->assertOk()
        ->assertJsonPath('id', $copy->id)
        ->assertJsonPath('qr_code', 'PRES-QR-003600');
});

it('can look up a book copy by a prefixed system barcode', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'qr_code' => 'PRES-QR-003601',
        'barcode' => 'PRES-BC-003601',
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr?qr_code='.urlencode($copy->barcode))
        ->assertOk()
        ->assertJsonPath('id', $copy->id)
        ->assertJsonPath('barcode', 'PRES-BC-003601');
});

it('can extract a prefixed code from a scanned qr payload', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'qr_code' => 'PRES-QR-003602',
    ]);

    $payload = 'https://library.local/scan?code=PRES-QR-003602';

    $this->actingAs($staff)
        ->getJson('/api/auth/book-copies/by-qr?qr_code='.urlencode($payload))
        ->assertOk()
        ->assertJsonPath('id', $copy->id)
        ->assertJsonPath('qr_code', 'PRES-QR-003602');
});
