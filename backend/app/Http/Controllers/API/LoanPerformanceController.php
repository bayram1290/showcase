<?php

namespace App\Http\Controllers\API;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use App\Helpers\ApiResponse;
use App\Http\Resources\LoanPerformanceResource;
use App\Contracts\Services\LoanPerformanceServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use Illuminate\Http\Request;

class LoanPerformanceController extends Controller
{
    use AuthorizesRequests;
    protected LoanPerformanceServiceInterface $service;

    public function __construct(
        LoanPerformanceServiceInterface $service
    ) {
        $this->service = $service;
    }

    public function show(Request $request, LoanAccount $loanAccount): JsonResponse
    {

        try {
            $this->authorize('view-performance', $loanAccount);

            $user = $request->user();
            $include_chart = $request->boolean('include_chart', false);
            $data = $this->service->getPerformanceData($loanAccount, $user, $include_chart);

            return ApiResponse::success(
                new LoanPerformanceResource($data, $user),
                'SHOW_LOAN_PERFORMANCE_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'SHOW_LOAN_PERFORMANCE_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
