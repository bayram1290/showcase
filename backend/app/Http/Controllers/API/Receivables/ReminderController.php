<?php

namespace App\Http\Controllers\API\Receivables;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

use App\Domain\Receivables\Contracts\ReceivablesServiceInterface;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Installment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReminderController extends Controller
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
     * Send a reminder for an installment
     *
     * @param Request $request The HTTP request instance
     * @param Installment $installment The installment to send a reminder for
     * @return JsonResponse The JSON response indicating the success of the operation
    */
    public function send(Request $request, Installment $installment): JsonResponse
    {
        $this->authorize('sendReminder', $installment);

        $this->service->sendReminder($installment, $request->user());

        return ApiResponse::success(null, 'Reminder sent successfully.', Response::HTTP_OK);
    }
}