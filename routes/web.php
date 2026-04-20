<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\BookCopyQrController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ReservationController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'library.context', 'role:super_admin,admin,staff'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    // BOOKS
    Route::get('/books', [BookController::class, 'index'])->name('books.index');
    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('/book-copies/{id}', [BookCopyController::class, 'showPage'])->name('book-copies.show');
    Route::get('/book-copies/{id}/qr', [BookCopyQrController::class, 'show'])->name('book-copies.qr');
    // BOOKS
    // LOANS
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/search-members', [LoanController::class, 'searchMembers'])->name('loans.search-members');
    Route::post('/book-copies/{bookCopy}/return', [LoanController::class, 'returnBook'])->name('loans.return');
    // RESERVATIONS
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

});

require __DIR__ . '/settings.php';
