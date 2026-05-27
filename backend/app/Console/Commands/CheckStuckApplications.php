<?php

namespace App\Console\Commands;

use App\Notifications\StuckApplicationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Models\LoanApplication;
use Carbon\Carbon;

class CheckStuckApplications extends Command
{
    private const THREE_DAYS_STR = 3;
    private const UNDER_REVIEW_STR = 'under review';


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'application:check-stuck-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify assigned officers about applications stuck in "under review" for more thatn 3 days';

    /**
     * Handle stuck loan applications that have been under review for three days or more.
     *
     * - Retrieves all loan applications that are under review and have not been updated in the last three days.
     * - Notify the assigned officer of each stuck application using the StuckApplicationNotification class.
     * - Log the number of stuck applications notified.
     *
     * @return int The number of stuck applications notified successfully.
     */
    public function handle(): int
    {
        $stucked_applications = LoanApplication::with(['assignedOfficer'])
                                ->where('status', self::UNDER_REVIEW_STR)
                                ->whereDate('updated_at', '<=', Carbon::now()->subDays(self::THREE_DAYS_STR))
                                ->get();

        if ($stucked_applications->isEmpty()) {
            $this->info('No stuck applications found.');
            Log::info('No stuck applications found for ' . Carbon::now()->toDateTimeString());
            return 0;
        }

        foreach ($stucked_applications as $application) {
            if ($application instanceOf LoanApplication) {
                $officer = $application->assigned_officer;
                if ($officer) {
                    $officer->notify(
                        new StuckApplicationNotification($application, self::THREE_DAYS_STR)
                    );
                }
            }
        }

        $this->info('Stuck applications notified successfully.');
        Log::info('Stuck (' . count($stucked_applications) . ') applications notfied for ' . Carbon::now()->toDateTimeString());
        return 0;
    }
}
