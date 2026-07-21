<?php

namespace App\Http\Controllers\API\Receivables;

use App\Http\Controllers\Controller;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Domain\Receivables\ValueObjects\OverdueFilterData;

use App\Helpers\ApiResponse;
use App\Http\Requests\Receivables\OverdueRequest;
use App\Models\Installment;

use Symfony\Component\HttpFoundation\Response;

class OverdueController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     *
     * @param ReceivablesServiceInterface $service The service responsible for performing operations on receivables
     * @return void
    */
    public function __construct(private readonly ReceivablesServiceInterface $service) {}

    /**
     * Get overdue installments based on the provided filter.
     *
     * @param OverdueRequest $request The HTTP request instance
     * @return JsonResponse The JSON response indicating the success of the operation
    */
    public function index(OverdueRequest $request): JsonResponse
    {
        Log::info($request->all());
        $this->authorize('viewOverdue', [Installment::class]);

        $filter = OverdueFilterData::fromRequest($request);
        $installments = $this->service->getOverdueInstallments($filter);

        return ApiResponse::success($installments, 'Overdue installments retrieved.', Response::HTTP_OK);
    }
}