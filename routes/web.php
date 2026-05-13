<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\BookCopyQrController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\ListExportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberLibraryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\Management\AuthorController as ManageAuthorController;
use App\Http\Controllers\Management\AuditLogController as ManageAuditLogController;
use App\Http\Controllers\Management\BookController as ManageBookController;
use App\Http\Controllers\Management\BookCopyController as ManageBookCopyController;
use App\Http\Controllers\Management\BranchController as ManageBranchController;
use App\Http\Controllers\Management\CategoryController as ManageCategoryController;
use App\Http\Controllers\Management\LibraryController as ManageLibraryController;
use App\Http\Controllers\Management\LocationController as ManageLocationController;
use App\Http\Controllers\Management\PublisherController as ManagePublisherController;
use App\Http\Controllers\Management\SearchController as ManageSearchController;
use App\Http\Controllers\Management\UserController as ManageUserController;
use App\Http\Controllers\Management\UserMembershipController as ManageUserMembershipController;
use App\Http\Controllers\Management\ImportController as ManageImportController;
use App\Http\Controllers\UserQrController;

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/apie', [PublicPageController::class, 'about'])->name('about');
Route::get('/bibliotekos', [PublicPageController::class, 'libraries'])->name('public.libraries.index');
Route::view('/pagalba', 'help')->name('help');

Route::middleware(['auth', 'overdue.notifications', 'verified', 'library.context', 'role:superadministratorius,administratorius,darbuotojas'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/export/{format}', DashboardExportController::class)->name('dashboard.export');
});

Route::middleware(['auth', 'overdue.notifications'])->group(function () {
    Route::post('/libraries/{library}/join', [MemberLibraryController::class, 'join'])->middleware(['verified'])->name('libraries.join');

    Route::get('/exports/{resource}.csv', ListExportController::class)->name('exports.list');

    Route::middleware(['verified', 'library.context', 'role:narys'])
        ->prefix('account')
        ->as('account.')
        ->group(function () {
            Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
            Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
            Route::get('/profile/qr', [UserQrController::class, 'show'])->name('profile.qr');
        });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
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

Route::middleware(['auth', 'overdue.notifications', 'verified', 'library.context', 'role:superadministratorius,administratorius,darbuotojas'])
    ->prefix('manage')
    ->as('manage.')
    ->group(function () {
        Route::get('search', [ManageSearchController::class, 'index'])->name('search.index');
        Route::get('imports/{resource}', [ManageImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{resource}', [ManageImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{resource}/template', [ManageImportController::class, 'template'])->name('imports.template');
        Route::get('books', fn () => redirect()->route('books.index', request()->query()))->name('books.index');
        Route::resource('books', ManageBookController::class)->except(['index', 'show']);
        Route::resource('authors', ManageAuthorController::class)->except('show');
        Route::resource('branches', ManageBranchController::class)->except('show');
        Route::resource('locations', ManageLocationController::class)->except('show');
        Route::resource('users', ManageUserController::class)->except(['store', 'update']);
        Route::patch('users/{user}/toggle-active', [ManageUserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/memberships', [ManageUserMembershipController::class, 'store'])->name('users.memberships.store');
        Route::patch('users/{user}/memberships/{membership}/toggle', [ManageUserMembershipController::class, 'toggle'])->name('users.memberships.toggle');
        Route::delete('users/{user}/memberships/{membership}', [ManageUserMembershipController::class, 'destroy'])->name('users.memberships.destroy');
        Route::get('book-copies', [ManageBookCopyController::class, 'index'])->name('book-copies.index');
        Route::get('book-copies/create', [ManageBookCopyController::class, 'create'])->name('book-copies.create');
        Route::post('book-copies', [ManageBookCopyController::class, 'store'])->name('book-copies.store');
        Route::get('book-copies/{bookCopy}/edit', [ManageBookCopyController::class, 'edit'])->name('book-copies.edit');
        Route::patch('book-copies/{bookCopy}/lifecycle', [ManageBookCopyController::class, 'updateLifecycle'])->name('book-copies.lifecycle.update');
        Route::put('book-copies/{bookCopy}', [ManageBookCopyController::class, 'update'])->name('book-copies.update');
        Route::delete('book-copies/{bookCopy}', [ManageBookCopyController::class, 'destroy'])->name('book-copies.destroy');
    });

Route::middleware(['auth', 'overdue.notifications', 'verified', 'library.context', 'role:superadministratorius'])
    ->prefix('manage')
    ->as('manage.')
    ->group(function () {
        Route::get('audit-logs', [ManageAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::resource('libraries', ManageLibraryController::class)->except('show');
        Route::post('libraries/{library}/staff', [ManageLibraryController::class, 'assignStaff'])->name('libraries.staff.store');
        Route::patch('libraries/{library}/staff/{user}/toggle', [ManageLibraryController::class, 'toggleStaff'])->name('libraries.staff.toggle');
        Route::delete('libraries/{library}/staff/{user}', [ManageLibraryController::class, 'destroyStaff'])->name('libraries.staff.destroy');
        Route::resource('categories', ManageCategoryController::class)->except('show');
        Route::resource('publishers', ManagePublisherController::class)->except('show');
    });

require __DIR__ . '/settings.php';

