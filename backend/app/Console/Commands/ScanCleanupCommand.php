<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\Document;
use Illuminate\Support\Facades\Log;

class ScanCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'document:scan-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark stale pending documents as error (older than 24 hours)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deadline_date = Carbon::now()->subDays(2);
        $count = Document::where('scan_status', 'uploaded')
                    ->where('created_at', '<', $deadline_date)
                    ->update([
                        'scan_status' => 'error',
                        'last_scanned_at' => Carbon::now(),
                    ]);

        Log::info("Cleanup: marked {$count} stale documents as error.");
        $this->info("Cleanup: marked {$count} stale documents as error.");

        return 0;
    }
}
