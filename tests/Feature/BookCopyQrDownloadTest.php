<?php

use App\Models\BookCopy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('book copy qr can be downloaded as an image file', function () {
    $copy = BookCopy::factory()->create([
        'inventory_code' => 'INV-QR-001',
        'qr_code' => 'QR-DOWNLOAD-001',
    ]);

    $staff = User::factory()->staff()->create([
        'library_id' => $copy->library_id,
    ]);

    $response = $this
        ->actingAs($staff)
        ->get(route('book-copies.qr', ['id' => $copy->id, 'download' => 1]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="INV-QR-001-qr.svg"')
        ->assertSee('svg', false);
});
