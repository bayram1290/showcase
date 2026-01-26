<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_application_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'is_verified',
        'verification_notes',
        'verified_by',
        'verified_at'
    ];

    protected $casts = [
        'file_size' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime'
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function verify(int $id, $notes = null): void
    {
        $user_id = User::findOrFail($id)->id;

        $this->update([
            'is_verified' => true,
            'verification_notes' => $notes,
            'verified_by' => $user_id,
            'verified_at' => Carbon::now()
        ]);
    }

    public function reject(int $id, $notes = null): void
    {
        $user_id = User::findOrFail($id)->id;

        $this->update([
            'is_verified' => false,
            'verification_notes' => $notes,
            'verified_by' => $user_id,
            'verified_at' => now()
        ]);
    }
}
