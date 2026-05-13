<?php

use Illuminate\Support\Facades\Route;

it('renders the Lithuanian 404 page', function () {
    $this->get('/puslapis-kurio-nera')
        ->assertNotFound()
        ->assertSee('Puslapis nerastas')
        ->assertSee('Grįžti į pradžią');
});

it('renders the Lithuanian 403 page', function () {
    Route::get('/test-403-page', fn () => abort(403));

    $this->get('/test-403-page')
        ->assertForbidden()
        ->assertSee('Neturite prieigos')
        ->assertSee('Į dashboard');
});

it('renders the Lithuanian 500 page', function () {
    Route::get('/test-500-page', fn () => abort(500));

    $this->get('/test-500-page')
        ->assertStatus(500)
        ->assertSee('Įvyko klaida')
        ->assertSee('Bandyti dar kartą');
});





