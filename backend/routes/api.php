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
    DashboardReportController,
    LoanPerformanceController,
    Receivables\NegotiationController,
    Receivables\OverdueController,
    Receivables\LateFeeController,
    Receivables\ReminderController,
    Receivables\DefaultController,
};

Route::prefix('v1')->group(function() {

    // Public routes
    Route::get('loan-products', [LoanProductController::class, 'index']);
    Route::get('loan-products/{loanProduct}/show', [LoanProductController::class, 'show']);
    Route::get('/documents/download/{document:uuid}', [DocumentController::class, 'downloadFile'])->name('documents.download')->where('uuid', config('helper.api_route.app_uuid_regex'));

    // System user auth routes
    Route::prefix('user')->group(function (){
        Route::post('login', [SystemAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [SystemAuthController::class, 'logout']);
            Route::get('profile', [SystemAuthController::class, 'profile']);
        });
    });

    // Admin routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function() {
        Route::get('users', [UserController::class, 'index']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/activate', [UserController::class, 'activate']);
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate']);
    });

    // Credit check routes
    Route::middleware(['auth:sanctum', 'role:loan_officer,officer'])->group(function() {
        Route::post('credit-check/internal', [CreditCheckController::class, 'internalCheck']);
        Route::post('credit-check/external', [CreditCheckController::class, 'externalCheck']);
        Route::get('user/credit-check/{application:application_uuid}', [CreditCheckController::class, 'checkForApplication'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
    });

    // Loan application management routes, 1st phase - initiation
    Route::prefix('applications/initiation')->middleware(['auth:sanctum', 'role:loan_officer,officer'])->group(function() {
        Route::get('/', [LoanApplicationController::class, 'index']);
        Route::get('{application:application_uuid}', [LoanApplicationController::class, 'show']);
        Route::post('{application:application_uuid}/under-review', [LoanApplicationController::class, 'underReviewLoan']);
        Route::post('{application:application_uuid}/assign', [LoanApplicationController::class, 'assignOfficer']);
    });

    // Loan application management routes, 2nd phase - initiation
    Route::prefix('applications/approval')->middleware(['auth:sanctum', 'role:loan_officer,supervisor'])->group(function() {
        Route::get('pending', [LoanApplicationController::class, 'pendingApplications']);
        Route::post('{application:application_uuid}/approve', [LoanApplicationController::class, 'approveLoan']);
        Route::post('{application:application_uuid}/reject', [LoanApplicationController::class, 'rejectLoan']);
        Route::post('{application:application_uuid}/disburse', [DisbursementController::class, 'disburseLoan']);
    });

    Route::middleware(['auth:sanctum', 'role:loan_officer,supervisor'])->group(function (){
        Route::get('applications/{application:application_uuid}/documents', [DocumentController::class, 'index']);
        Route::post('documents/{document:uuid}/verify', [DocumentController::class, 'verify'])->where('document', config('helper.api_route.app_uuid_regex'));
    });

    // Borrower routes
    Route::prefix('borrower')->group(function () {
        Route::post('register', [BorrowerAuthController::class, 'register']);
        Route::post('login', [BorrowerAuthController::class, 'login']);

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware(['throttle:6,1', 'auth:borrower'])->name('verification.resend');

        Route::middleware(['auth:borrower', 'verified'])->group(function () {
            Route::post('logout', [BorrowerAuthController::class, 'logout']);
            Route::get('profile', [BorrowerAuthController::class, 'profile']);

            // Borrower applications
            Route::prefix('applications')->group(function() {
                Route::post('/', [LoanApplicationController::class, 'store']);
                Route::get('/', [LoanApplicationController::class, 'myApplications']);
                Route::get('{application:application_uuid}', [LoanApplicationController::class, 'show']);
                Route::post('{application:application_uuid}/submit', [LoanApplicationController::class, 'submit']);
                Route::post('{application:application_uuid}/cancel', [LoanApplicationController::class, 'cancel']);
                Route::post('{application:application_uuid}/restore', [LoanApplicationController::class, 'restoreToDraft']);

                Route::prefix('{application:application_uuid}/documents')->group(function (){
                    Route::get('/', [DocumentController::class, 'index']);
                    Route::post('/', [DocumentController::class, 'upload'])
                    ->middleware('throttle:documents.upload'); //Upload document (rate limited 15 per day application)
                    Route::get('{document:uuid}/download', [DocumentController::class, 'download']);
                    Route::delete('{document:uuid}', [DocumentController::class, 'destroy']);
                });
            });

            // Credit check route
            Route::get('credit-check/{application:application_uuid}', [CreditCheckController::class, 'checkForApplication'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));

            // Loan accounts installments
            Route::prefix('loan-accounts')->group(function() {
                Route::get('{application:application_uuid}/show', [LoanAccountController::class, 'showInstallments'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
                Route::post('{installment:installment_uuid}/repayment', [RepaymentController::class, 'makeRepayment']);
            });
        });
    });

    Route::prefix('loan-accounts')->group(function() {
        Route::middleware('auth:sanctum')->group(function(){
            Route::get('{application:application_uuid}/show', [LoanAccountController::class, 'showInstallments'])->middleware(['role:loan_officer,officer,supervisor'])->where('application_uuid', config('helper.api_route.app_uuid_regex'));
            Route::post('{installment:installment_uuid}/repayment', [RepaymentController::class, 'makeRepayment'])->middleware(['role:cashier']);
        });

        Route::middleware('auth:sanctum,borrower')->group(function(){
            Route::get('{loanAccount:account_uuid}/performance', [LoanPerformanceController::class, 'show'])->middleware(['throttle:60,1']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:collector,loan_officer,supervisor'])->prefix('collections')->group(function () {
        Route::get('overdue-installments', [OverdueController::class, 'index']);
        Route::post('installments/{installment:installment_uuid}/waive-late-fee', [LateFeeController::class, 'waive']);
        Route::post('installments/{installment:installment_uuid}/send-reminder', [ReminderController::class, 'send']);
        Route::post('loans/{loanAccount:account_uuid}/negotiate', [NegotiationController::class, 'store']);
        Route::post('loans/{loanAccount:account_uuid}/mark-default', [DefaultController::class, 'mark']);
        Route::post('loans/{loanAccount:account_uuid}/restore', [DefaultController::class, 'restore']);
    });

    // TODO: Test these routes for real data on tables
    Route::middleware('auth:sanctum')->prefix('reports')->group(function () {
        Route::get('dashboard', [DashboardReportController::class, 'dashboard'])
            ->middleware(['role:manager', 'throttle:30,1']);

        Route::get('approved-loans', [DashboardReportController::class, 'approvedLoans'])
            ->middleware(['role:manager,supervisor', 'throttle:60,1']);

        Route::get('npa', [DashboardReportController::class, 'npaLoans'])
                ->middleware(['role:manager,supervisor', 'throttle:60,1']);

        Route::get('export/approved-loans', [DashboardReportController::class, 'exportApprovedLoans'])
                ->middleware(['role:manager,supervisor', 'throttle:10,1']);
    });

    // Moderator routes
    Route::prefix('loan-products')->middleware(['auth:sanctum', 'role:moderator'])->group(function() {
        Route::post('/', [LoanProductController::class, 'store']);
        Route::put('{loanProduct}', [LoanProductController::class, 'update']);
        Route::delete('{loanProduct}', [LoanProductController::class, 'destroy']);
        Route::patch('{loanProduct}/status', [LoanProductController::class, 'updateStatus']);
    });
});

Route::fallback(function () {
    return response()->json(['message' => 'Endpoint not found'], 404);
});