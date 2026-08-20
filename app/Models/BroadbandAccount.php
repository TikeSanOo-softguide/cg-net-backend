<?php

namespace App\Models;

use App\Enums\BroadbandAccountStatus;
use Database\Factories\BroadbandAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'account_number',
    'customer_name',
    'status',
    'current_package_id',
])]
class BroadbandAccount extends Model
{
    /** @use HasFactory<BroadbandAccountFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => BroadbandAccountStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'current_package_id');
    }

    public function cpeDevices(): HasMany
    {
        return $this->hasMany(CpeDevice::class);
    }

    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function failureReports(): HasMany
    {
        return $this->hasMany(FailureReport::class);
    }

    public function relocationRequests(): HasMany
    {
        return $this->hasMany(RelocationRequest::class);
    }

    public function changePlanRequests(): HasMany
    {
        return $this->hasMany(ChangePlanRequest::class);
    }
}
