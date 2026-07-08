<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'loan_application_id',
        'document_type',
        'file_path',
        'file_name',
        'original_file_name',
        'mime_type',
        'file_size',
        'scan_status',
        'last_scanned_at',
        'thumbnail_path',
        'is_verified',
        'verification_notes',
        'verified_at',
        'verified_by',
        'vt_analysis_id',
        'scan_attempts',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'file_size' => 'integer',
        'scan_attempts' => 'integer',
    ];

    protected $appends = ['file_size_human', 'file_url', 'thumbnail_url'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();;
            }
        });
    }

    public function getRouteKey(): string
    {
        return 'uuid';
    }

    // Relationships
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }


    // Accessors
    public function getFileSizeHumanAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $bytes = $this->file_size;
        $factor = 0;

        while ($bytes >= 1024 && $factor < count($units) - 1) {
            $bytes /= 1024;
            $factor++;
        }

        return round($bytes, 2) . ' ' . $units[$factor];
    }

    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path . '/' . $this->file_name);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path): null;
    }

    public function markScanResult(string $status): void
    {
        $valid_statuses = $this->scanStatuses();

        if (!in_array($status, $valid_statuses)) {
            throw new \InvalidArgumentException('Invalid scan status.');
        }

        $this->update(['scan_status' => $status, 'last_scanned_at' => Carbon::now()]);
    }

    // Helpers

    public function isImage(): bool
    {
        return in_array(
            $this->mime_type,
            ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp']
        );
    }

    public function isVerified(): bool
    {
        return (bool) $this->is_verified;
    }

    public function isCleaned(): bool
    {
        return $this->scan_status === 'clean';
    }

    public function isInfected(): bool
    {
        return $this->scan_status === 'infected';
    }

    public function isPendingScan(): bool
    {
        return $this->scan_status === 'pending';
    }

    public function scanStatuses(): array
    {

        $cache_key = 'document_scan_status_enum_values';

        if (Cache::has($cache_key)) {
            return Cache::get($cache_key);
        }

        $db_name = DB::connection()->getDatabaseName();

        $enum = DB::select("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ", [$db_name, 'documents', 'scan_status']);

        if (empty($enum)) {
            $columns = DB::select("SHOW COLUMNS FROM documents LIKE 'scan_status'");

            if (!empty($columns)) {
                $type = [$columns[0]->Type];
            } else {
                return [];
            }
        } else {
            $type = $enum[0]->COLUMN_TYPE ?? '';
        }

        preg_match_all("/'([^']+)'/", $type, $matches);
        $enum_values = $matches[1] ?? [];

        Cache::put($cache_key, $enum_values, \Carbon\Carbon::now()->addMonth());

        return $enum_values;
    }

    public static function clearScanStatusEnumCache(): void
    {
        Cache::forget('document_scan_status_enum_values');
    }
}
