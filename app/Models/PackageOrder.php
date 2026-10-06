<?php

namespace App\Models;

use App\Enums\PackageOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([
    'user_id',
    'package_id',
    'ledger_transaction_id',
    'status',
    'snapshot',
    'external_response',
    'completed_at',
])]
class PackageOrder extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('PackageOrder records cannot be deleted.');
        });
    }

    public $timestamps = false;

    protected $table = 'package_orders';

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'external_response' => 'array',
            'completed_at' => 'datetime',
            'status' => PackageOrderStatus::class,
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

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function customerPackage(): HasOne
    {
        return $this->hasOne(CustomerPackage::class, 'package_order_id');
    }
}
