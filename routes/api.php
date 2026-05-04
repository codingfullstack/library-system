<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookCopyController as ApiBookCopyController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'library.context'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    Route::middleware(['auth:sanctum', 'library.context'])->group(function () {
        Route::get('/books/{book}', [BookController::class, 'show']);
        Route::get('/books', [BookController::class, 'index']);
        Route::get('/book-copies/{bookCopy}', [ApiBookCopyController::class, 'show']);
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
        Route::post('/book-copies/{bookCopy}/borrow', [LoanController::class, 'borrow']);
        Route::post('/book-copies/{bookCopy}/return', [LoanController::class, 'returnBook']);
        Route::patch('/book-copies/{bookCopy}/lifecycle', [ApiBookCopyController::class, 'updateLifecycle']);
        Route::get('/members/search', [LoanController::class, 'searchMembers']);
        Route::get('/loans/active', [LoanController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::get('/member/dashboard', MemberDashboardController::class);
        Route::post('/scan', [ScanController::class, 'scan']);
        Route::get('/book-copies/by-qr/{qrCode}', [ApiBookCopyController::class, 'findByQr']);
    });
});
