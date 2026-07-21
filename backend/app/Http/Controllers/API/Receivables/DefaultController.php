<?php

namespace App\Http\Controllers\API\Receivables;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
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
     * Mark a loan account as defaulted
     *
     * @param Request $request The HTTP request instance
     * @param LoanAccount $loanAccount The loan account to be marked as defaulted
     * @throws \Illuminate\Auth\Access\AuthorizationException If the user is not authorized to mark the loan account as defaulted
     * @return \Illuminate\Http\JsonResponse The JSON response indicating the success of the operation
     */
    public function mark(Request $request, LoanAccount $loanAccount)
    {
        $this->authorize('markDefault', $loanAccount);

        $reason = $request->input('reason');
        $this->service->markDefault($loanAccount, $request->user(), $reason);

        return ApiResponse::success(null, 'Loan marked as defaulted', Response::HTTP_OK);
    }

    /**
     * Restore a loan account to active status
     *
     * @param Request $request The HTTP request instance
     * @param LoanAccount $loanAccount The loan account to be restored
     * @throws \Illuminate\Auth\Access\AuthorizationException If the user is not authorized to restore the loan account
     * @return \Illuminate\Http\JsonResponse The JSON response indicating the success of the operation
     */
    public function restore(Request $request, LoanAccount $loanAccount)
    {
        $this->authorize('restore', $loanAccount);

        $reason = $request->input('reason');
        $this->service->restore($loanAccount, $request->user(), $reason);

        return ApiResponse::success(null, 'Loan restored to active', Response::HTTP_OK);
    }
}