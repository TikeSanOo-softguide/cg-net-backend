<?php

namespace App\Models;

use App\Enums\WalletActorType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
    ]),
]
class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'integer',
            'status' => WalletTransactionStatus::class,
            'actor_type' => WalletActorType::class,
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

    public function walletEntry(): HasOne
    {
        return $this->hasOne(WalletEntry::class);
    }

    public function walletTransfer(): HasOne
    {
        return $this->hasOne(WalletTransfer::class);
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
        return $this->hasOne(TopUpCard::class, 'wallet_transaction_id')->withTrashed();
    }
}
