<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[
    Fillable([
        'ledger_transaction_id',
        'ledger_account_id',
        'wallet_id',
        'debit',
        'credit',
        'line_no',
        'balance_before',
        'balance_after',
    ]),
]
class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $table = 'ledger_entries';

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('LedgerEntry records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'line_no' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isDebit(): bool
    {
        return (int) $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return (int) $this->credit > 0;
    }

    public function amount(): int
    {
        return (int) max($this->debit, $this->credit);
    }
}
