<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    protected $fillable = [
        'challenge_id',
        'phone',
        'otp_hash',
        'provider_reference',
        'purpose',
        'expires_at',
        'failed_attempts',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'phone', 'phone');
    }
}
