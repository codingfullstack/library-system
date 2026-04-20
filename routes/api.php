<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\BookCopyController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'library.context'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', function (Request $request) {
            return response()->json($request->user());
        });
    });
    Route::middleware(['auth:sanctum', 'library.context'])->group(function () {
        Route::get('/books/{book}', [BookController::class, 'show']);
        Route::get('/books', [BookController::class, 'index']);
        Route::post('/book-copies/{bookCopy}/borrow', [LoanController::class, 'borrow']);
        Route::post('/book-copies/{bookCopy}/return', [LoanController::class, 'returnBook']);
        Route::get('/members/search', [LoanController::class, 'searchMembers']);
        Route::get('/loans/active', [LoanController::class, 'index']);
        Route::get(
            '/book-copies/by-qr/{qrCode}',
            [BookCopyController::class, 'findByQr']
        );
    });
});