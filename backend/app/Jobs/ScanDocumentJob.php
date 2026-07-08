<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\Document;

class ScanDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Document $document;

    public function __construct(
        Document $document
    ) {
        $this->document = $document;
    }

    /**
     * Handle the document upload and scan with VirusTotal.
     *
     * Handle the document upload and scan with VirusTotal.
     * - check the document's scan status and skips the upload if it is not pending or uploaded.
     * - retrieve the VirusTotal API key from the configuration and marks the document as clean if the key is missing.
     * - check if the document file exists on the disk and marks the scan result as error if the file is missing.
     * - upload the document file to VirusTotal and marks the scan result as error if the upload request fails.
     * - mark the scan result as error if the VirusTotal upload response fails.
     * - retrieve the analysis ID from the VirusTotal response and updates the document with the ID.
     * - log the successful upload and analysis ID.
     *
     * @return void
     * @throws \Exception If an error occurs during the upload or scan.
     */
    public function handle(): void
    {
        if (!in_array($this->document->scan_status, ['pending', 'uploaded'])) {
            Log::info("Upload job skipped for document {$this->document->id} – status: {$this->document->scan_status}");
            return;
        }

        $api_key = config('services.virustotal.api_key');
        if (!$api_key) {
            $this->document->markScanResult('clean');
            Log::warning('VirusTotal API key missing. Marking document as clean.');
            return;
        }

        $file_path = $this->document->file_path . '/' . $this->document->file_name;
        $disk = 'public';
        if (!Storage::disk($disk)->exists($file_path)) {
            $this->document->markScanResult('error');
            Log::error("Document file missing: {$file_path}");
            return;
        }

        $file_content = Storage::disk($disk)->get($file_path);
        $api_url = config('services.virustotal.api_url');
        try {

            $response = Http::withHeaders([
                'x-apikey' => $api_key,
                'accept' => 'application/json'
            ])->attach(
                'file',
                $file_content,
                $this->document->file_name
            )->post($api_url);
        } catch (\Exception $e) {
            $this->document->markScanResult('error');
            Log::error("VirusTotal upload request failed: " . $e->getMessage());
            return;
        }

        if ($response->failed()) {
            $this->document->markScanResult('error');
            Log::error("VirusTotal upload response failed: " . $response->body());
            return;
        }

        $analysis_id = $response->json('data.id');
        if (!$analysis_id) {
            $this->document->markScanResult('error');
            Log::error("VirusTotal upload did not return analysis ID.");
            return;
        }

        $this->document->update([
            'vt_analysis_id' => $analysis_id,
            'scan_attempts' => 0,
            'scan_status' => 'uploaded',
        ]);

        Log::info("VirusTotal upload successful for document {$this->document->id}. Analysis ID: {$analysis_id}");
    }
}