<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows only superadmin to edit existing books', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create(['title' => 'Tik superadmin redaguoja']);

    $this->actingAs($admin)
        ->get(route('manage.books.edit', $book))
        ->assertForbidden();

    $this->actingAs($staff)
        ->put(route('manage.books.update', $book), [
            'title' => 'Bandymas pakeisti',
            'subtitle' => '',
            'isbn' => $book->isbn,
            'description' => $book->description,
            'publisher_id' => '',
            'publication_year' => '',
            'language' => 'lt',
            'page_count' => '',
            'edition' => '',
            'cover_image' => '',
            'author_ids' => [],
            'category_ids' => [],
        ])
        ->assertForbidden();

    expect($book->fresh()->title)->toBe('Tik superadmin redaguoja');

    $this->actingAs($superAdmin)
        ->get(route('manage.books.edit', $book))
        ->assertOk()
        ->assertSee('Redaguoti knygą');
});

it('lets staff open a catalog book detail before their library has a copy', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create([
        'title' => 'Dar nepridėta knyga',
        'isbn' => '9786090000000',
    ]);

    $this->actingAs($staff)
        ->withSession(['active_library_id' => $library->id])
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Dar nepridėta knyga')
        ->assertSee('9786090000000');
});

it('allows only superadmin to delete existing books', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create(['title' => 'Trinama tik superadmin']);

    $this->actingAs($admin)
        ->delete(route('manage.books.destroy', $book))
        ->assertForbidden();

    $this->assertDatabaseHas('books', [
        'id' => $book->id,
        'title' => 'Trinama tik superadmin',
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('manage.books.destroy', $book))
        ->assertRedirect(route('books.index'));

    $this->assertDatabaseMissing('books', [
        'id' => $book->id,
    ]);
});

it('hides book edit and delete actions from admin and staff book pages', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Matoma knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
    ]);

    $this->actingAs($admin)
        ->get(route('books.index'))
        ->assertOk()
        ->assertDontSee(route('manage.books.edit', $book), false)
        ->assertDontSee(route('manage.books.destroy', $book), false);

    $this->actingAs($staff)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertDontSee('Redaguoti knygą')
        ->assertDontSee('Ištrinti knygą');
});





