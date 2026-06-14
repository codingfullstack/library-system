<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookCopyController as ApiBookCopyController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicLibraryController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\UserMembershipScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

    Route::middleware(['auth:sanctum', 'library.context', 'throttle:api-sensitive'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/dashboard/summary', DashboardSummaryController::class);
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
    });
    Route::middleware(['auth:sanctum', 'library.context', 'throttle:api-read'])->group(function () {
        Route::get('/books/{book:id}', [BookController::class, 'show'])->whereNumber('book');
        Route::get('/books', [BookController::class, 'index']);
        Route::get('/book-copies/{bookCopy}', [ApiBookCopyController::class, 'show'])->whereNumber('bookCopy');
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:api-sensitive');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('throttle:api-sensitive');
        Route::post('/book-copies/{bookCopy}/borrow', [LoanController::class, 'borrow'])->middleware('throttle:api-sensitive');
        Route::post('/book-copies/{bookCopy}/return', [LoanController::class, 'returnBook'])->middleware('throttle:api-sensitive');
        Route::patch('/book-copies/{bookCopy}/lifecycle', [ApiBookCopyController::class, 'updateLifecycle'])->middleware('throttle:api-sensitive');
        Route::get('/members/search', [LoanController::class, 'searchMembers']);
        Route::get('/loans/active', [LoanController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('throttle:api-sensitive');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->middleware('throttle:api-sensitive');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('throttle:api-sensitive');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('throttle:api-sensitive');
        Route::get('/member/dashboard', MemberDashboardController::class);
        Route::get('/libraries/public', [PublicLibraryController::class, 'index']);
    });

    Route::middleware(['auth:sanctum', 'library.context', 'throttle:api-sensitive'])->group(function () {
        Route::post('/libraries/{library}/join', [PublicLibraryController::class, 'join']);
        Route::post('/scan', [ScanController::class, 'scan']);
        Route::get('/book-copies/by-qr', [ApiBookCopyController::class, 'findByQr']);
        Route::get('/book-copies/by-qr/{qrCode}', [ApiBookCopyController::class, 'findByQr'])->where('qrCode', '.*');
        Route::get('/members/by-membership/{membershipNumber}', [UserMembershipScanController::class, 'show']);
        Route::post('/memberships/scan', [UserMembershipScanController::class, 'store']);
    });
});
