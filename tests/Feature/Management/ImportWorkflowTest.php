<?php

use App\Models\Book;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('collects book import row errors and continues importing later rows', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    Book::factory()->create(['title' => 'Esama knyga', 'isbn' => 'ISBN-EXISTS']);

    $csv = implode("\n", [
        'title;isbn;author_slugs',
        'Pirma knyga;ISBN-FIRST;pirmas-autorius',
        'Dublikatas;ISBN-EXISTS;',
        ';ISBN-MISSING-TITLE;',
        'Antra knyga;ISBN-SECOND;antras-autorius',
    ]);

    $response = $this->actingAs($staff)
        ->post(route('manage.imports.store', 'books'), [
            'file' => UploadedFile::fake()->createWithContent('books.csv', $csv),
        ]);

    $response->assertRedirect(route('books.index'));
    $response->assertSessionHas('import_report', function (array $report): bool {
        return $report['created'] === 2
            && $report['skipped'] === 1
            && $report['failed'] === 1
            && collect($report['details'])->contains(fn (array $detail) => $detail['line'] === 4 && $detail['status'] === 'klaida');
    });

    $this->assertDatabaseHas('books', ['title' => 'Pirma knyga']);
    $this->assertDatabaseHas('books', ['title' => 'Antra knyga']);
    $this->assertDatabaseMissing('books', ['isbn' => 'ISBN-MISSING-TITLE']);
});

it('collects branch import row errors without stopping the import', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    Branch::factory()->create(['library_id' => $library->id, 'name' => 'Esamas filialas', 'code' => 'EXIST']);

    $csv = implode("\n", [
        'name;code;address;city',
        'Naujas filialas;NEW;Gatve 1;Vilnius',
        'Dublikatas;EXIST;Gatve 2;Vilnius',
        'Be kodo;;Gatve 3;Vilnius',
        'Dar vienas;NEXT;Gatve 4;Kaunas',
    ]);

    $response = $this->actingAs($staff)
        ->post(route('manage.imports.store', 'branches'), [
            'file' => UploadedFile::fake()->createWithContent('branches.csv', $csv),
        ]);

    $response->assertRedirect(route('manage.branches.index'));
    $response->assertSessionHas('import_report', function (array $report): bool {
        return $report['created'] === 2
            && $report['skipped'] === 1
            && $report['failed'] === 1
            && collect($report['details'])->contains(fn (array $detail) => $detail['line'] === 4 && $detail['status'] === 'klaida');
    });

    $this->assertDatabaseHas('branches', ['library_id' => $library->id, 'code' => 'NEW']);
    $this->assertDatabaseHas('branches', ['library_id' => $library->id, 'code' => 'NEXT']);
});

it('collects location import row errors without stopping the import', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Pagrindinis', 'code' => 'MAIN']);
    Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id, 'name' => 'Esama vieta', 'code' => 'EXIST']);

    $csv = implode("\n", [
        'branch_code;branch_name;name;code;room;shelf;description',
        'MAIN;;Nauja vieta;NEW;1;A;',
        'MAIN;;Dublikatas;EXIST;1;B;',
        'MISSING;;Nerastas filialas;MISS;2;C;',
        'MAIN;;Dar viena;NEXT;3;D;',
    ]);

    $response = $this->actingAs($staff)
        ->post(route('manage.imports.store', 'locations'), [
            'file' => UploadedFile::fake()->createWithContent('locations.csv', $csv),
        ]);

    $response->assertRedirect(route('manage.locations.index'));
    $response->assertSessionHas('import_report', function (array $report): bool {
        return $report['created'] === 2
            && $report['skipped'] === 1
            && $report['failed'] === 1
            && collect($report['details'])->contains(fn (array $detail) => $detail['line'] === 4 && $detail['status'] === 'klaida');
    });

    $this->assertDatabaseHas('locations', ['library_id' => $library->id, 'branch_id' => $branch->id, 'code' => 'NEW']);
    $this->assertDatabaseHas('locations', ['library_id' => $library->id, 'branch_id' => $branch->id, 'code' => 'NEXT']);
});





