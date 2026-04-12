<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\AdminRestoreRequest;
use App\Http\Requests\CancelLoanApplicationRequest;
use App\Http\Requests\CreateLoanApplicationRequest;
use App\Http\Requests\RestoreLoanApplicationRequest;
use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Http\Requests\UpdateLoanStatusRequest;
use App\Models\Borrower;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanApplicationService;
use App\Services\OfficerAssignmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoanApplicationController extends Controller
{
    protected LoanApplicationService $loanService;
    protected OfficerAssignmentService $officerService;

    public function __construct(
        LoanApplicationService $service,
        OfficerAssignmentService $officerService
    ) {
        $this->loanService = $service;
        $this->officerService = $officerService;
    }

    /**
     * Creates a draft loan application.
     *
     * @param CreateLoanApplicationRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(CreateLoanApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->loanService->createDraft($request->user(), $request->validated());

            return ApiResponse::success($application, 'CREATE_DRAFT_APPLICATION_SUCCESS', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'CREATE_DRAFT_APPLICATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Submits a loan application.
     *
     * @param SubmitLoanApplicationRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function submit(SubmitLoanApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $loan_application = $this->loanService->submit($application, $request->user());

            return ApiResponse::success(
                $loan_application,
                'SUBMIT_APPLICATION_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'SUBMIT_APPLICATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Return a paginated list of loan applications by the current user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myApplications(Request $request): JsonResponse{
        $applications = $request->user()->loanApplications()
                        ->with(['loanProduct', 'assignedOfficer'])
                        ->orderByDesc('created_at')
                        ->paginate(config('helper.default_pagination_length'));

        return ApiResponse::success($applications, 'LIST_LOAN_APPLICATIONS_SUCCESS', Response::HTTP_OK);
    }

    /**
     * Returns a loan application based on the user's permissions/the borrower request.
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function show(Request $request, LoanApplication $application): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Borrower) {

            if ($application->borrower_id !== $user->id) {
                return ApiResponse::error(
                    'You do not have permission to view this loan application.',
                    'SHOW_APPLICATION_ERROR',
                    Response::HTTP_FORBIDDEN
                );
            }

            $application->load(['loanProduct']);
        }

        if ($user instanceof User) {

            if ((!$user->isLoanOfficer() && $application->assigned_officer_id !== $user->id)) {
                return ApiResponse::error(
                    'You are not authorized to view this loan application.',
                    'SHOW_APPLICATION_ERROR',
                    Response::HTTP_FORBIDDEN
                );
            }

            $application->load(['loanProduct', 'assignedOfficer', 'documents', 'loanAccount', 'creditCheck']);
        }

        return ApiResponse::success($application, 'SHOW_APPLICATION_SUCCESS', Response::HTTP_OK);
    }

    /**
     * Returns a paginated list of loan applications filtered by the request parameters.
     * Parameters accepted are:
     *   status: The status of the loan application (draft, submitted, under_review, approved, rejected, cancelled, disbursed, closed)
     *   borrower_id: The ID of the borrower
     *   date_from: The earliest date the loan application was created
     *   date_to: The latest date the loan application was created
     *   search: Search for a loan application based on:
     *      - application ref
     *      - borrower's first name or,
     *      - borrower's last name or,
     *      - borrower's email, or phone
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoanApplication::with(['borrower', 'loanProduct', 'assignedOfficer']);
        $user = $request->user();

        if (!$user->isLoanOfficer()) $query->where('assigned_officer_id', $user->id);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('borrower_id')) $query->where('borrower_id', $request->borrower_id);
        if ($request->has('date_from')) $query->where('created_at', '>=', $request->date_from);
        if ($request->has('date_to')) $query->where('created_at', '<=', $request->date_to);
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('application_ref', 'like', "%{$search}%")
                  ->orWhereHas('borrower', function($borrower_query) use ($search) {
                      $borrower_query->where('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name', 'like', "%{$search}%")
                                     ->orWhere('email', 'like', "%{$search}%")
                                     ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $application = $query->orderByDesc('created_at')->paginate(config('helper.default_pagination_length'));

        return ApiResponse::success($application, 'LIST_LOAN_APPLICATIONS_SUCCESS', Response::HTTP_OK);
    }

    /**
     * Updates the status of a loan application.
     *
     * @param UpdateLoanStatusRequest $request
     * @param LoanApplication $application
     * @return JsonResponse
     * @throws \Exception
     */
    public function updateStatus(UpdateLoanStatusRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $this->loanService->updateStatus($application, $request->validated(), $request->user());

            return ApiResponse::success($application, 'UPDATE_APPLICATION_STATUS_SUCCESS', Response::HTTP_OK);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPDATE_APPLICATION_STATUS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Assigns a loan officer to a loan application.
     *
     * @param LoanApplication $application
     * @return JsonResponse
     * @throws \Exception
     */
    public function assignOfficer(LoanApplication $application): JsonResponse
    {
        try {
            $this->officerService->assignOfficer($application);

            return ApiResponse::success(
                null,
                'ASSIGN_LOAN_OFFICER_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'ASSIGN_LOAN_OFFICER_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Cancels a loan application.
     *
     * @param CancelLoanApplicationRequest $request
     * @param LoanApplication $application
     * @return JsonResponse
     * @throws \Exception
     */
    public function cancel(CancelLoanApplicationRequest $request, LoanApplication $application): JsonResponse {
        try {
            $this->loanService->cancel($application, $request->user(), $request->validated(['cancellation_reason']));

            return ApiResponse::success(
                null,
                'CANCEL_APPLICATION_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'CANCEL_APPLICATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Restore a loan application to a draft state after being cancelled.
     * Throws an error if:
     * - the application is not cancelled or the current user does not own the application.
     * - the cancelation date has passed more than 30 days.
     * - the loan product is not active or no longer available.
     *
     * @param RestoreLoanApplicationRequest $request
     * @param int $id The ID of the loan application
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function restoreToDraft(RestoreLoanApplicationRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $application = $this->loanService->restoreByBorrower($application->id, $request->user(), $request->validated('update_data', []));

            return ApiResponse::success(
                $application,
                'RESTORE_APPLICATION_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'RESTORE_APPLICATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function managerRestore(AdminRestoreRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $this->loanService->restoreByAdmin($application, $request->validated(), $request->user());

            return ApiResponse::success(
                null,
                'ADMIN_RESTORE_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'ADMIN_RESTORE_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
