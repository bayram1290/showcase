<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'action',
        'old_data',
        'new_data',
        'user_id',
        'loan_application_id',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    // Methods
    public static function log(
        string $action,
        $new_data = null,
        $old_data = null,
        $user_id = null,
        $application_id = null
    ): AuditLog {

        $request = request();

        return self::create([
            'action'=> $action,
            'old_data' => $old_data,
            'new_data' => $new_data,
            'user_id' => $user_id ?? auth()->id(),
            'loan_application_id' => $application_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
    }

    public function scopeForApplication($query, $user_id): Builder
    {
        return $query->where('user_id', $user_id);
    }

}
