<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\{
    LoanApplicationController,
    LoanProductController,
    DocumentController,
    DashboardController,
    LoanAccountController,
    AdminController,
    CreditCheckController,
    AuthController,
    UserController,
    BorrowerController
};


# Public endpoints
Route::post('/login', [AuthController::class, 'login']); // done
Route::post('/borrower/register', action: [AuthController::class, 'registerBorrower']); // done
Route::post('/borrower/login', [AuthController::class, 'borrowerLogin']); // done
Route::get('/loan-products', [LoanProductController::class, 'index']); // done

// Logout routes via auth.api
Route::middleware(['throttle:10,1', 'auth.api'])->group(function () {
    Route::post('/user/logout', [AuthController::class, 'logout']); // done
    Route::post('/borrower/logout', [AuthController::class, 'logout']); // done
});

Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    // Auth
    Route::get('/profile', [AuthController::class, 'profile']);

    // Admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']); // under development

        Route::get('/users', [UserController::class, 'index']); // done
        Route::put('/users/{id}', [UserController::class, 'update']); // done
        Route::delete('/users/{id}', [UserController::class, 'delete']); // done
        Route::post('/users/{id}/activate', [UserController::class, 'activate']); // done
        Route::post('/users/{id}/deactivate', [UserController::class, 'deactivate']); // done
    });

    // Admin & loan officer
    Route::middleware(['role:admin,loan_officer'])->group(function (): void {
        Route::get('/applications', [LoanApplicationController::class, 'index']); //
        Route::get('/applications/{id}', [LoanApplicationController::class, 'show']); //
        Route::put('/applications/{id}/status', [LoanApplicationController::class, 'updateStatus']); //
        Route::post('/applications/{id}/assign', [LoanApplicationController::class, 'assignOfficer']); //
        Route::post('/credit-check/{application_id}', [CreditCheckController::class, 'performCreditCheck']); //
        Route::put('/documents/{document_id}/verify', [DocumentController::class, 'verifyDocument']); //

        // Borrower management
        Route::get('/borrowers', [BorrowerController::class, 'index']); // done
        Route::get('/borrowers/{id}', [BorrowerController::class, 'show']); // done
        Route::put('/borrowers/{id}/verify', [BorrowerController::class, 'verify']); // done
        Route::put('/borrowers/{id}/block', [BorrowerController::class, 'block']); // done
        Route::put('/borrowers/{id}/unblock', [BorrowerController::class, 'unblock']); // done
    });

    Route::get('/dashboard/stats', [DashboardController::class, 'adminStats']); // done
    Route::get('/monthly-trend', [DashboardController::class, 'monthlyTrend']); //
});

Route::middleware(['auth:sanctum', 'borrower'])->prefix('borrower')->group(function (): void {
    Route::post('/profile', [AuthController::class,'profile']);

    // Loan applications
    Route::get('/applications', [LoanApplicationController::class, 'myApplications']);
    Route::post('/applications', [LoanApplicationController::class, 'create']);
    Route::get('/applications/{id}', [LoanApplicationController::class, 'show']);
    Route::post('/applications/{id}/submit', [LoanApplicationController::class, 'submit']);
    Route::post('/applications/{id}/cancel', [LoanApplicationController::class, 'cancel']);

    // Documents
    Route::post('/applications/{id}/documents', [DocumentController::class, 'upload']);
    Route::get('/applications/{id}/documents', [DocumentController::class, 'getDocuments']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'borrowerStats']);
});