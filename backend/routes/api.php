<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\{
    LoanApplicationController,
    DocumentController,
    CreditCheckController,
    UserController,
    SystemAuthController,
    BorrowerAuthController,
    EmailVerificationController,
    LoanProductController,
    // DashboardController,
    LoanAccountController,
    // BorrowerController,
    DisbursementController,
    RepaymentController,
};

Route::get('loan-products', [LoanProductController::class, 'index']);
Route::get('loan-products/{id}', [LoanProductController::class, 'show']);

// System user auth routes
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

// Loan officer and officer routes
Route::middleware(['auth:sanctum', 'role:loan_officer,officer'])->group(function() {

    Route::post('/documents/{document}/verify', [DocumentController::class, 'verify']);
    // Route::post('/applications/{application:application_uuid}/management-restore', [LoanApplicationController::class, 'managerRestore']);

    // Credit check routes
    Route::post('/credit-check/internal', [CreditCheckController::class, 'internalCheck']);
    Route::post('/credit-check/external', [CreditCheckController::class, 'externalCheck']);
    Route::get('/user/credit-check/{application:application_uuid}', [CreditCheckController::class, 'checkForApplication'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
});

// Loan application management routes
Route::prefix('applications/management')->group(function() {

    Route::middleware(['auth:sanctum', 'role:loan_officer,officer'])->group(function() {
        Route::get('/', [LoanApplicationController::class, 'index']);
        Route::get('/{application:application_uuid}', [LoanApplicationController::class, 'show']);
        Route::post('/{application:application_uuid}/under_review', [LoanApplicationController::class, 'underReviewLoan']);
        Route::post('/{application:application_uuid}/assign', [LoanApplicationController::class, 'assignOfficer']);
    });
});

Route::middleware(['auth:sanctum', 'role:loan_officer,supervisor'])->group(function() {
    Route::prefix('applications/management')->group(function() {
        Route::get('/pending', [LoanApplicationController::class, 'pendingApplications']);
        Route::post('/{application:application_uuid}/approve', [LoanApplicationController::class, 'approveLoan']);
        Route::post('/{application:application_uuid}/reject', [LoanApplicationController::class, 'rejectLoan']);
        Route::post('/{application:application_uuid}/disburse', [DisbursementController::class, 'disburseLoan']);
    });
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

        // Borrower applications
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

        // Credit check route
        Route::get('/credit-check/{application:application_uuid}', [CreditCheckController::class, 'checkForApplication'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));

        // Loan accounts installments
        Route::prefix('loan-accounts')->group(function() {
            Route::get('/{application:application_uuid}/show', [LoanAccountController::class, 'showInstallments'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
            Route::post('/{installment:installment_uuid}/repayment', [RepaymentController::class, 'makeRepayment']);
        });
    });
});

Route::prefix('loan-accounts')->middleware('auth:sanctum')->group(function() {
    Route::get('/{application:application_uuid}/show', [LoanAccountController::class, 'showInstallments'])->middleware(['role:loan_officer,officer,supervisor'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
    Route::post('/{installment:installment_uuid}/repayment', [RepaymentController::class, 'makeRepayment'])->middleware(['role:cashier']);
});

// Moderator routes
Route::prefix('loan-products')->middleware(['auth:sanctum', 'role:moderator'])->group(function() {
    Route::post('/', [LoanProductController::class, 'store']);
    Route::put('/{loanProduct}', [LoanProductController::class, 'update']);
    Route::delete('/{loanProduct}', [LoanProductController::class, 'destroy']);
    Route::patch('/{loanProduct}/status', [LoanProductController::class, 'updateStatus']);
});