<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['code', 'name', 'type', 'wallet_id', 'is_postable', 'is_active'])]
class LedgerAccount extends Model
{
    protected $table = 'ledger_accounts';

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('LedgerAccount records cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => LedgerAccountType::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isCustomerLiability(): bool
    {
        return $this->type === LedgerAccountType::Liability && $this->wallet_id !== null;
    }
}
