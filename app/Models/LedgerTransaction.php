<?php

namespace App\Models;

use App\Enums\WalletActorType;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use Database\Factories\LedgerTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[
    Fillable([
        'wallet_id',
        'transaction_no',
        'type',
        'status',
        'amount',
        'idempotency_key',
        'reversal_of',
        'related_transaction_id',
        'note',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'posted_at',
    ]),
]
class LedgerTransaction extends Model
{
    /** @use HasFactory<LedgerTransactionFactory> */
    use HasFactory;

    protected $table = 'ledger_transactions';

    protected function casts(): array
    {
        return [
            'type' => LedgerTransactionType::class,
            'amount' => 'integer',
            'status' => LedgerTransactionStatus::class,
            'actor_type' => WalletActorType::class,
            'posted_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    public function relatedAdjustments(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function billPayment(): HasOne
    {
        return $this->hasOne(BillPayment::class);
    }

    public function packageOrder(): HasOne
    {
        return $this->hasOne(PackageOrder::class);
    }

    public function topUpCard(): HasOne
    {
        return $this->hasOne(TopUpCard::class, 'ledger_transaction_id')->withTrashed();
    }

    /** Liability line for the primary wallet, if present. */
    public function walletLiabilityEntry(): ?LedgerEntry
    {
        return $this->entries->first(
            fn (LedgerEntry $entry) => $entry->wallet_id === $this->wallet_id
                && $entry->ledgerAccount?->isCustomerLiability(),
        );
    }
}
