<?php

namespace App\Console\Commands;

use App\Services\DocumentService;
use Illuminate\Console\Command;

class PurgeDeletedDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:purge-deleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete permanently soft-deleted documents and their files older than 6 months';

    /**
     * Execute the console command.
     */
    public function handle(DocumentService $service): int
    {
        $count = $service->purgeSoftDeletedDocuments();
        $this->info("{$count} soft-deleted documents are purged permanently.");
        return 0;
    }
}
