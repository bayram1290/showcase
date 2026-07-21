<?php

namespace App\Http\Controllers\API\Receivables;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Installment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class LateFeeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Construct a new controller instance.
     *
     * @param ReceivablesServiceInterface $service The service responsible for performing operations on receivables.
     * @return void
     */
    public function __construct(private readonly ReceivablesServiceInterface $service) {}

    /**
     * Waive a late fee for an installment.
     * 
     * @param Request $request The HTTP request instance.
     * @param Installment $installment The installment to waive the late fee for.
     * @throws AuthorizationException If the user is not authorized to waive the late fee.
     * @return JsonResponse The JSON response indicating the success of the operation.
     */
    public function waive(Request $request, Installment $installment): JsonResponse
    {
        $this->authorize('waiveLateFee', $installment);

        $reason = $request->input('reason');
        $this->service->waiveLateFee($installment, $request->user(), $reason);

        return ApiResponse::success(null, 'Late fee waived successfully.', Response::HTTP_OK);
    }
}