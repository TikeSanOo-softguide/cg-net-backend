<?php

namespace App\Models;

use App\Enums\TopUpCardStatus;
use Database\Factories\TopUpCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[
    Fillable([
        'serial_no',
        'pin',
        'amount',
        'expires_at',
        'redeemed_at',
        'redeemed_by',
        'status',
        'office_id',
        'batch_id',
        'ledger_transaction_id',
    ]),
]
#[Hidden(['pin'])]
class TopUpCard extends Model
{
    /** @use HasFactory<TopUpCardFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'top_up_card';

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('TopUpCard records cannot be deleted.');
        });
        static::forceDeleting(static function (): never {
            throw new LogicException('TopUpCard records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'redeemed_at' => 'datetime',
            'status' => TopUpCardStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }
}
