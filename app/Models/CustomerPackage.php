<?php

namespace App\Models;

use App\Enums\CustomerPackageStatus;
use Database\Factories\CustomerPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'broadband_account_id',
    'package_id',
    'start_date',
    'expiry_date',
    'auto_renew',
    'status',
])]
class CustomerPackage extends Model
{
    /** @use HasFactory<CustomerPackageFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiry_date' => 'date',
            'auto_renew' => 'boolean',
            'status' => CustomerPackageStatus::class,
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
