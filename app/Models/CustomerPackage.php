<?php

namespace App\Models;

use App\Enums\CustomerPackageStatus;
use App\Models\PackageOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[
    Fillable([
        'user_id',
        'package_id',
        'package_order_id',
        'username',
        'password',
        'start_date',
        'expiry_date',
        'expired_at',
        'auto_renew',
        'status',
    ]),
]
class CustomerPackage extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerPackageFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiry_date' => 'date',
            'expired_at' => 'datetime',
            'auto_renew' => 'boolean',
            'status' => CustomerPackageStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function packageOrder(): BelongsTo
    {
        return $this->belongsTo(PackageOrder::class, 'package_order_id');
    }
}
