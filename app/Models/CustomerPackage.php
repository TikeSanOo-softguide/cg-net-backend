<?php

namespace App\Models;

use App\Enums\CustomerPackageStatus;
use Database\Factories\CustomerPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[
    Fillable([
        'user_id',
        'package_id',
        'package_order_id',
        'username',
        'password',
        'starts_at',
        'expires_at',
        'status',
    ]),
]
#[Hidden(['password'])]
class CustomerPackage extends Model
{
    /** @use HasFactory<CustomerPackageFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('CustomerPackage records cannot be deleted.');
        });
        static::forceDeleting(static function (): never {
            throw new LogicException('CustomerPackage records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
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
