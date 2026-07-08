<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScanPollCommand extends Command
{
    protected const MAX_ATTEMPTS = 20;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'document:scan-poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll VirusTotal for pending document scans (rate-limited to 4 requests/min)';

    /**
     * Execute the console command to poll VirusTotal for pending document scans.
     *
     * Retrieve a list of uploaded documents that have a null or non-null VirusTotal analysis ID
     * and a scan attempts count less than the maximum allowed attempts.
     * Then, poll the VirusTotal API for  each document's analysis status and update the document's scan status accordingly.
     *
     * @return int The exit code of the command execution.
     */
    public function handle()
    {
        $documents = Document::where('scan_status', 'uploaded')
                    ->whereNotNull('vt_analysis_id')
                    ->where('scan_attempts', '<', self::MAX_ATTEMPTS)
                    ->orderBy('created_at')
                    ->get();

        if ($documents->isEmpty()) {
            $this->info('No documents to scan.');
            return 0;
        }

        $this->info('Found ' . $documents->count() . ' uploaded documents to be scanned.');

        $processed = 0;
        $api_key = config('services.virustotal.api_key');
        if (!$api_key) {
            $this->error('VirusTotal API key missing.');
            return 1;
        }

        $rate_key = (string) config('services.virustotal.rate_key');
        $limit = (int) config('services.virustotal.polls_per_minute');
        $ttl = 60;

        foreach ($documents as $document) {

            if (!Cache::has($rate_key)) {
                Cache::put($rate_key, $limit, $ttl);
            }

            $tokens = Cache::get($rate_key);
            if ($tokens <= 0) {
                $this->warn('Rate limit exceeded for ' . $rate_key);
                break;
            }

            Cache::decrement($rate_key);
            $analysis_id = $document->vt_analysis_id;

            try {
                $response = Http::withHeaders(['x-apikey' => $api_key,])
                            ->get(config('services.virustotal.api_analysis_url') . '/' . $analysis_id);
            } catch (\Exception $e) {
                Log::error("Polling request failed for document {$document->id}: " . $e->getMessage());
                $document->increment('scan_attempts');
                continue;
            }

            if ($response->failed()) {
                Log::error("VirusTotal poll failed for document {$document->id}: " . $response->body());
                $document->increment('scan_attempts');
                continue;
            }

            $status = $response->json('data.attributes.status');
            if ($status === 'completed') {
                $stats = $response->json('data.attributes.stats');
                $malicious = $stats['malicious'];
                $suspicious = $stats['suspicious'];

                if ($malicious > 0 || $suspicious > 0) {
                    $document->markScanResult('infected');
                    Log::warning("Document {$document->id} infected (malicious: {$malicious}, suspicious: {$suspicious})");
                } else {
                    $document->markScanResult('clean');
                    Log::info("Document {$document->id} is clean.");
                }

                $processed++;
                continue;
            }

            if ($status === 'error') {
                $document->markScanResult('error');
                Log::error("VirusTotal analysis error for document {$document->id}");
                $processed++;
                continue;
            }

            $document->increment('scan_attempts');
            Log::info("Document {$document->id} analysis still pending (attempt {$document->scan_attempts})");
        }

        $this->info("Processed {$processed} documents in this run.");
        return 0;
    }
}
