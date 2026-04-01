<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\{
    // LoanApplicationController,
    // LoanProductController,
    // DocumentController,
    // DashboardController,
    // LoanAccountController,
    // AdminController,
    // CreditCheckController,
    // AuthController,
    // UserController,
    // BorrowerController
    SystemAuthController,
    BorrowerAuthController,
    EmailVerificationController
};

// System user routes
Route::prefix('user')->group(function (){
    Route::post('/login', [SystemAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SystemAuthController::class, 'logout']);
        Route::get('/profile', [SystemAuthController::class, 'profile']);
    });
});


Route::prefix('borrower')->group(function () {
    Route::post('/register', [BorrowerAuthController::class, 'register']);
    Route::post('/login', [BorrowerAuthController::class, 'login']);

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware(['throttle:6,1', 'auth:borrower'])->name('verification.resend');

    Route::middleware(['auth:borrower', 'verified'])->group(function () {
        Route::post('/logout', [BorrowerAuthController::class, 'logout']);
        Route::get('/profile', [BorrowerAuthController::class, 'profile']);
    });
});
