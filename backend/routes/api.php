<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\{
    LoanApplicationController,
    DocumentController,
    // DashboardController,
    // LoanAccountController,
    // CreditCheckController,
    // AuthController,
    UserController,
    // BorrowerController
    SystemAuthController,
    BorrowerAuthController,
    EmailVerificationController,
    LoanProductController,
};

Route::get('loan-products', [LoanProductController::class, 'index']);
Route::get('loan-products/{id}', [LoanProductController::class, 'show']);

// System user routes
Route::prefix('user')->group(function (){
    Route::post('/login', [SystemAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SystemAuthController::class, 'logout']);
        Route::get('/profile', [SystemAuthController::class, 'profile']);
    });
});

// Admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function() {
    Route::get('/users', [UserController::class, 'index']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::post('/users/{user}/activate', [UserController::class, 'activate']);
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate']);
});

Route::middleware(['auth:sanctum', 'role:loan_officer,officer'])->group(function() {

    Route::prefix('applications/management')->group(function() {
        Route::get('/', [LoanApplicationController::class, 'index']);
        Route::get('/{application:application_uuid}', [LoanApplicationController::class, 'show']);
        Route::post('/{application:application_uuid}/status', [LoanApplicationController::class, 'updateStatus']); // Approval, Rejection are under process
        Route::post('/{application:application_uuid}/assign', [LoanApplicationController::class, 'assignOfficer']);
    });

    Route::post('/documents/{document}/verify', [DocumentController::class, 'verify']);
    // Route::post('/applications/{application:application_uuid}/management-restore', [LoanApplicationController::class, 'managerRestore']);
});

// Borrower routes
Route::prefix('borrower')->group(function () {
    Route::post('/register', [BorrowerAuthController::class, 'register']);
    Route::post('/login', [BorrowerAuthController::class, 'login']);

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware(['throttle:6,1', 'auth:borrower'])->name('verification.resend');

    Route::middleware(['auth:borrower', 'verified'])->group(function () {
        Route::post('/logout', [BorrowerAuthController::class, 'logout']);
        Route::get('/profile', [BorrowerAuthController::class, 'profile']);

        Route::prefix('applications')->group(function() {
            Route::post('/', [LoanApplicationController::class, 'store']);
            Route::get('/', [LoanApplicationController::class, 'myApplications']);
            Route::get('/{application:application_uuid}', [LoanApplicationController::class, 'show']);
            Route::post('/{application:application_uuid}/submit', [LoanApplicationController::class, 'submit']);
            Route::post('/{application:application_uuid}/cancel', [LoanApplicationController::class, 'cancel']);
            Route::post('/{application:application_uuid}/restore', [LoanApplicationController::class, 'restoreToDraft']);

            Route::post('/{application:application_uuid}/documents', [DocumentController::class, 'upload']);
            Route::delete('/{application:application_uuid}/documents/{document}', [DocumentController::class, 'delete']);
        });
    });
});

Route::prefix('loan-products')->middleware(['auth:sanctum', 'role:moderator'])->group(function() {
    Route::post('/', [LoanProductController::class, 'store']);
    Route::put('/{loanProduct}', [LoanProductController::class, 'update']);
    Route::delete('/{loanProduct}', [LoanProductController::class, 'destroy']);
    Route::patch('/{loanProduct}/status', [LoanProductController::class, 'updateStatus']);
});

