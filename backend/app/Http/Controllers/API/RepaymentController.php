<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use App\Helpers\ApiResponse;
use App\DataTransferObjects\RepaymentData;
use App\Http\Requests\InstallmentRepaymentRequest;
use App\Http\Resources\InstallmentRepaymentResource;
use App\Contracts\Services\RepaymentServiceInterface;
use App\Models\Installment;

class RepaymentController extends Controller
{
    use AuthorizesRequests;

    /**
     * RepaymentService constructor.
     *
     * @param RepaymentServiceInterface $service The repayment service implementation.
     */
    public function __construct(
        protected RepaymentServiceInterface $service
    ) {}

    /**
     * Make a repayment.
     *
     * @param InstallmentRepaymentRequest $request The repayment request.
     * @return JsonResponse The JSON response containing the repayment result.
     * @throws \Exception If an error occurs during the repayment process.
     */
    public function makeRepayment(InstallmentRepaymentRequest $request, Installment $installment): JsonResponse
    {
        try {

            $installment->load('loanAccount.loanApplication');
            $user = $request->user() ?? request()->user();
            $this->authorizeForUser($user, 'updateInstallmentForRepayment', $installment);

            $installment_dto = RepaymentData::fromRequest($request, $installment);
            $installment = $this->service->performRepayment($installment_dto);
            return ApiResponse::success(
                new InstallmentRepaymentResource($installment, $request->user()),
                'REPAYMENT_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'REPAYMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
