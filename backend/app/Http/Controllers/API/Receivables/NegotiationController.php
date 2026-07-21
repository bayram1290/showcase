<?php

namespace App\Http\Controllers\API\Receivables;


use App\Http\Controllers\Controller;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use \Illuminate\Http\JsonResponse;

use App\Http\Requests\Receivables\NegotiationRequest;
use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Domain\Receivables\ValueObjects\NegotiationData;
use App\Helpers\ApiResponse;
use App\Models\LoanAccount;

use Symfony\Component\HttpFoundation\Response;

class NegotiationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Construct a new controller instance
     *
     * @param ReceivablesServiceInterface $service The service responsible for performing operations on receivables
     * @return void
     */
    public function __construct(private readonly ReceivablesServiceInterface $service) {}

    /**
     * Record a negotiation for a loan account
     * 
     * @param NegotiationRequest $request The HTTP request instance
     * @param LoanAccount $loanAccount The loan account to record the negotiation for
     * @return JsonResponse The JSON response indicating the success of the operation
    */
    public function store(NegotiationRequest $request, LoanAccount $loanAccount): JsonResponse
    {
        $this->authorize('negotiate', $loanAccount);

        $data = NegotiationData::fromRequest($request);
        $this->service->negotiate($loanAccount, $data, $request->user());

        return ApiResponse::success(null, 'Negotiation recorded successfully', Response::HTTP_CREATED);
    }
}