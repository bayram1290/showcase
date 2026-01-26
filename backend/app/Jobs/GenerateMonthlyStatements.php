<?php

namespace App\Jobs;

use App\Mail\MonthlyStatementMail;
use App\Models\Installment;
use App\Models\LoanAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateMonthlyStatements implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct()
    { }

    public function handle(): void
    {
        $prev_month = Carbon::now()->subMonth();
        $month_start = $prev_month->copy()->startOfMonth();
        $month_end = $prev_month->copy()->endOfMonth();
        $month_name = $prev_month->format('F Y');

        $laon_accounts = LoanAccount::with(['loanApplication.user', 'installments'])
            ->active()->get();

        foreach ($laon_accounts as $loan_account) {
            try {
                $user = $loan_account->user;

                $transactions = Installment::where('loan_account_id', $loan_account->id)
                    ->where(function ($query) use ($month_start, $month_end) {
                        $query->whereBetween('due_date', [$month_start, $month_end])
                            ->orWhereBetween('paid_date', [$month_start, $month_end]);
                    })
                    ->orderBy('due_date', 'asc')->get();

                    if ($transactions->isEmpty()) {
                        continue;
                    }

                    $statement_details = [
                        'statement_period' => $month_name,
                        'generated_date' => Carbon::now()->format('d M Y'),
                        'customer' => [
                            'name' => $user->name,
                            'email' => $user->email,
                            'account_number' => $user->account_number
                        ],
                        'account_summary' => [
                            'opening_balance' => $loan_account->outstanding_balance + $transactions->where('paid_date', '>=', $month_start)->sum('paid_amount'),
                            'closing_balance' => $loan_account->outstanding_balance,
                            'total_principal_paid' => $transactions->where('paid_date', '>=', $month_start)->sum('principal_amount'),
                            'total_intrest_paid' => $transactions->where('paid_date', '>=', $month_start)->sum('interest_amount'),
                            'total_late_fees' => $transactions->where('paid_date', '>=', $month_start)->sum('late_fee'),
                        ],
                        'transactions' => $transactions->map(function ($transaction): array {
                            return [
                                'date' =>  $transaction->paid_date ?? $transaction->due_date,
                                'description' => "Installment #{$transaction->installment_number}",
                                'type' => $transaction->paid_date ? 'payment' : 'due',
                                'amount' => $transaction->paid_amount ?? $transaction->due_amount,
                                'status' => $transaction->status,
                            ];
                        })->toArray(),
                        'upcoming_payments' => Installment::where('loan_account_id', $loan_account->id)
                            ->where('status', 'pending')
                            ->where('due_date', '>', $month_end)
                            ->orderBy('due_date','asc')
                            ->limit(3)
                            ->get()
                            ->map(function($installment): array {
                                return [
                                    'due_date' => $installment->due_date->format('d M Y'),
                                    'amount' => $installment->due,
                                    'installment_number' => $installment->installment_number
                                ];
                        })->toArray(),
                    ];

                    $statement_content = $this->composeStatementPDF($statement_details);
                    $filename = "statements/{$loan_account->account_number}/{$month_name}.pdf";
                    Storage::disk('local')->put($filename, $statement_content);

                    DB::table('loam_statements')->insert([
                        'loan_account_id' => $loan_account->id,
                        'statement_period' => $month_name,
                        'file_path' => $filename,
                        'generated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at'=> Carbon::now(),
                    ]);

                    Mail::to($user->email)->send(new MonthlyStatementMail($statement_content, $filename));
            } catch (\Exception $e) {
                Log::error("Failed to generate statement for account {$loan_account->id}: {$e->getMessage()}");
            }
        }
    }

    private function composeStatementPDF(array $statement_content): string {
        return '';
    }
}
