<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\RelocationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'broadband_account_id',
    'current_address',
    'new_address',
    'preferred_date',
    'phone',
    'details',
    'status',
])]
class RelocationRequest extends Model
{
    /** @use HasFactory<RelocationRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => ReviewStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadbandAccount(): BelongsTo
    {
        return $this->belongsTo(BroadbandAccount::class);
    }
}
