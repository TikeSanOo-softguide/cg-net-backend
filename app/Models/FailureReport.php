<?php

namespace App\Models;

use App\Enums\FailureType;
use App\Enums\ReviewStatus;
use Database\Factories\FailureReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'broadband_account_id',
    'failure_type',
    'description',
    'photo_paths',
    'contact_name',
    'contact_phone',
    'status',
])]
class FailureReport extends Model
{
    /** @use HasFactory<FailureReportFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'failure_type' => FailureType::class,
            'photo_paths' => 'array',
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
