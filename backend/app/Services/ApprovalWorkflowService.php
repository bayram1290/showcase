<?php

namespace App\Services;

use App\Contracts\Services\ApprovalWorkflowServiceInterface;
use App\DataTransferObjects\ApprovalData;
use App\Events\LoanApplicationApproved;
use App\Events\LoanApplicationRejected;
use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApprovalWorkflowService implements ApprovalWorkflowServiceInterface
{

    public const PENDING_STR = 'under_review';
    public const APPROVE_STR = 'approved';
    public const REJECT_STR = 'rejected';

    public function approve(ApprovalData $data): LoanApplication
    {
        $application = LoanApplication::findOrFail($data->loanApplicationID);
        $user = User::findOrFail($data->userID);

        if ($application->status !== self::PENDING_STR) {
            throw new \Exception("Application is not satisfied for approval.");
        }

        Gate::authorize('approveApplication', $application);

        return DB::transaction(function() use ($application, $user, $data) {
            $application->update([
                'status' => self::APPROVE_STR,
                'approved_at' => Carbon::now(),
                'review_notes' => json_encode($data->remarks), // convert to json
            ]);

            event(new LoanApplicationApproved($application, $user, $data->remarks));

            return $application->fresh();
        });
    }

    public function reject(ApprovalData $data): LoanApplication
    {
        $application = LoanApplication::findOrFail($data->loanApplicationID);
        $user = User::findOrFail($data->userID);

        if ($application->status !== self::PENDING_STR) {
            throw new \Exception("Application is not satisfied for approval.");
        }

        Gate::authorize('rejectApplication', $application);

        return DB::transaction(function () use ($application, $user, $data) {
            $application->update([
                'status' => self::REJECT_STR,
                'rejection_notes' => $data->remarks,
                'closed_at' => Carbon::now(),
            ]);

            event(new LoanApplicationRejected($application, $user, $data->remarks));

            return $application->fresh();
        });
    }

    public function getPendingApplications(array $filters = []): LengthAwarePaginator
    {
        $query = LoanApplication::with(['borrower', 'loanProduct', 'assignedOfficer'])
                    ->where('status', 'under_review');

        if (isset($filters['officer_id'])) {
            $query->where('assigned_officer_id', $filters['officer_id']);
        }

        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('borrower', function ($q) use ($filters) {
                    $q->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%");
                    })
                    ->orWhereHas('loanProduct', function ($q) use ($filters) {
                        $q->where('name', 'like', "%{$filters['search']}%");
                    });

            });
        }

        return $query->orderBy('created_at')->paginate($filters['per_page'] ?? config('helper.default_pagination_length'));
    }
}