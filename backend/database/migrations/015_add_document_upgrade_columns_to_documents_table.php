<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FILE_UNIT_PATTERN = '/^([0-9.]+)\s*(B|KB|MB|GB|KiB|MiB|GiB)?$/i';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->convertFileSizeToBytes();

        Schema::table('documents', function(Blueprint $table) {
            $table->unsignedBigInteger('file_size')->change();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id')->nullable();
            $table->string('original_file_name')->after('file_name')->nullable();
            $table->enum('scan_status', ['pending', 'uploaded', 'clean', 'infected', 'error'])->default('pending')->after('file_size');
            $table->timestamp('last_scanned_at')->nullable()->after('scan_status');
            $table->string('thumbnail_path')->nullable()->after('last_scanned_at');
            $table->softDeletes()->after('updated_at');
            $table->string('vt_analysis_id')->nullable()->after('last_scanned_at');
            $table->tinyInteger('scan_attempts')->default(0)->after('vt_analysis_id');

            $table->unsignedBigInteger('file_size')->change();
        });

        DB::table('documents')->whereNull('uuid')->orderBy('id')->each(function ($document) {
            DB::table('documents')
                ->where('id', $document->id)
                ->update([
                    'uuid' => (string) Str::uuid(),
                    'scan_status' => 'clean',
                    'original_file_name' => $document->file_name
                ]);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->string('original_file_name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'uuid',
                'original_file_name',
                'scan_status',
                'last_scanned_at',
                'thumbnail_path',
                'deleted_at',
                'vt_analysis_id',
                'scan_attempts',
            ]);

            $table->string('file_size')->change();
        });
    }


    // Helper functions

    /**
     * Convert the file size from a human-readable format to bytes.
     *
     * @return void
     */
    private function convertFileSizeToBytes(): void
    {
        $documents = DB::table('documents')->get(['id', 'file_size']);

        foreach($documents as $document) {

            $doc_bytes = $this->parseFileSizeToBytes($document->file_size);
            DB::table('documents')
                ->where('id', $document->id)
                ->update(['file_size' => $doc_bytes]);
        }
    }

    /**
     * Parse a human-readable file size string to bytes.
     *
     * @param string|null $sizeString The file size string to parse.
     * @return int The file size in bytes.
     */
    private function parseFileSizeToBytes(?string $sizeString): int
    {
        if (empty($sizeString)) {
            return 0;
        }

        if (is_numeric($sizeString)) {
            return (int) $sizeString;
        }

        $sizeString = trim(str_replace(',', '.', $sizeString));

        if (preg_match(self::FILE_UNIT_PATTERN, $sizeString, $matches)) {
            $numeric_value = (float) $matches[1];
            $unit = strtoupper($matches[2] ?? 'B');

            return (int) match($unit) {
                'B' => $numeric_value,
                'KB', 'KIB' => $numeric_value * 1024,
                'Mb', 'MIB' => $numeric_value * 1024 * 1024,
                'GB', 'GIB' => $numeric_value * 1024 * 1024 * 1024,
                default => $numeric_value,
            };
        }

        return (int) $sizeString;
    }
};
