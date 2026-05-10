<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Contracts\Services\DisbursementServiceInterface;
use App\DataTransferObjects\DisbursementData;
use App\Exceptions\DisbursementException;
use App\Helpers\ApiResponse;
use App\Http\Requests\DisbursementRequest;
use App\Http\Resources\LoanAccountResource;
use App\Models\LoanApplication;
use Symfony\Component\HttpFoundation\Response;

class DisbursementController extends Controller
{

    protected DisbursementServiceInterface $service;
    public function __construct(
        DisbursementServiceInterface $service
    ) {
        $this->service = $service;
    }

    public function disburseLoan(DisbursementRequest $request, LoanApplication $application) {
        try {
            $dto = DisbursementData::fromRequest([
                'loan_application_id' => $application->id,
                'disbursed_by_user_id' => $request->user()->id,
                'remarks' => $request->validated('remarks'),
            ]);

            $loan_account = $this->service->disburse($dto);
            $loan_account->load('installments');

            return ApiResponse::success(
                new LoanAccountResource($loan_account, $request->user()),
                'DISBURSEMENT_SUCCESS',
                Response::HTTP_OK
            );

        } catch (DisbursementException $disbursement_exception) {
            return ApiResponse::error(
                $disbursement_exception->getMessage(),
                'DISBURSEMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'An unexpected error occurred.',
                'DISBURSEMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
