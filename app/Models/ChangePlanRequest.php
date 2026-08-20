<?php

namespace App\Models;

use App\Enums\ChangePlanStatus;
use Database\Factories\ChangePlanRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'broadband_account_id',
    'current_package_id',
    'new_package_id',
    'preferred_date',
    'phone',
    'note',
    'status',
])]
class ChangePlanRequest extends Model
{
    /** @use HasFactory<ChangePlanRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => ChangePlanStatus::class,
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

    public function currentPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'current_package_id');
    }

    public function newPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'new_package_id');
    }
}
