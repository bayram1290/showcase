<?php

namespace App\Http\Controllers\API;

use App\Contracts\Services\CreditCheckServiceInterface;
use App\DataTransferObjects\CreditCheckData;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalCreditCheckRequest;
use App\Http\Requests\InternalCreditCheckRequest;
use App\Models\Borrower;
use App\Models\User;
use App\Models\LoanApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditCheckController extends Controller
{
    protected CreditCheckServiceInterface $service;

    public function __construct(
        CreditCheckServiceInterface $service
    ) {
        $this->service = $service;
    }

    /**
     * Calculate internal credit score for a given loan application.
     * The internal credit score is calculated using the credit check service.
     *
     * @param InternalCreditCheckRequest $request
     * @return JsonResponse
     */
    public function internalCheck(InternalCreditCheckRequest $request): JsonResponse
    {
        try {
            $application = $request->getLoanApplication();
            $dto = new CreditCheckData(
                loanApplicationId: $application->id,
                checkedByUserId: request()->user()?->id,
                remarks: $request->validated('remarks')
            );

            $credit_check = $this->service->calculateInternalScore($dto);
            return ApiResponse::success($credit_check, 'INTERNAL_CREDIT_CHECK_SUCCESS', Response::HTTP_OK);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'CREDIT_CHECK_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Fetch external credit score from an external credit agency.
     * The external credit score is fetched using the credit check service.
     *
     * @param ExternalCreditCheckRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function externalCheck(ExternalCreditCheckRequest $request): JsonResponse
    {
        try {
            $external_data = $this->service->fetchExternalScore($request->ssn);

            return ApiResponse::success(
                $external_data,
                'EXTERNAL_CREDIT_CHECK_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'EXTERNAL_CREDIT_CHECK_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Retrieve credit check for a loan application (by loan application UUID)
     * Authorisation depends on the authenticated user (staff or borrower)
     * Allowed users: Borrower (owner of the application), Loan Officer or Officer (who is assigned to the application)
     *
     * @param LoanApplication $application
     * @return JsonResponse
     */
    public function checkForApplication(LoanApplication $application): JsonResponse
    {
        $user = request()->user();
        $return_data = [
            'id',
            'credit_score',
        ];
        if ($user instanceof Borrower) {
            if ($application->borrower_id !== $user->id) {
                return ApiResponse::forbidden('You are forbidden from accessing this application');
            }

        } else if (!$user->isLoanOfficer() && $user->id !== $application->assigned_officer_id) {
            return ApiResponse::forbidden('You are not assigned to this application. Access denied.');
        }

        $credit_check = $this->service->getForApplication($application->id);

        if (!$credit_check) {
            return ApiResponse::error('No credit check found for this application', 'NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            $return_data = array_merge($return_data, [
                'credit_report_data',
                'debt_to_income_ratio',
                'remarks',
                'created_at',
                'loan_application_id',
                'checked_by'
            ]);
        }

        return ApiResponse::success($credit_check->only($return_data), 'CREDIT_CHECK_SUCCESS', Response::HTTP_OK);
    }
}