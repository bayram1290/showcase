<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


use App\Http\Controllers\API\{
    LoanApplicationController,
    LoanProductController,
    DocumentController,
    DashboardController,
    LoanAccountController,
    AdminController,
    CreditCheckController,
};

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

# Public endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/loan-products', [LoanProductController::class, 'index']);


Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Loan Applications
    Route::prefix('applications')->group(function () {
        Route::get('/', [LoanApplicationController::class, 'myApplications']);
        Route::post('/', [LoanApplicationController::class, 'create']);
        Route::get('/{id}', [LoanApplicationController::class, 'show']);
        Route::post('/{id}/submit', [LoanApplicationController::class, 'submit']);

        // Documents
        Route::post('/{id}/documents', [DocumentController::class, 'upload']);
        Route::get('/{id}/documents', [DocumentController::class, 'getDocuments']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'customerStats']);

    // Admin/Loan Officer Routes
    Route::middleware(['role:loan_officer,admin'])->group(function () {
        Route::get('/admin/applications', [LoanApplicationController::class, 'index']);
        Route::put('/admin/applications/{id}/status', [LoanApplicationController::class, 'updateStatus']);

        // Credit Checks
        Route::post('/credit-check/{applicationId}', [CreditCheckController::class, 'performCreditCheck']);
        Route::get('/credit-check/{applicationId}', [CreditCheckController::class, 'getCreditCheck']);

        // Document Verification
        Route::put('/documents/{documentId}/verify', [DocumentController::class, 'verifyDocument']);

        // Dashboard
        Route::get('/admin/dashboard/stats', [DashboardController::class, 'adminStats']);
    });

    // Admin Only Routes
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('loan-products', LoanProductController::class)->except(['index', 'show']);
        Route::get('/admin/users', [AdminController::class, 'getUsers']);
        Route::put('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);

        // Loan Accounts Management
        Route::post('/loan-accounts/disburse/{applicationId}', [LoanAccountController::class, 'disburse']);
        Route::get('/loan-accounts', [LoanAccountController::class, 'index']);
        Route::get('/loan-accounts/{id}', [LoanAccountController::class, 'show']);
    });


});