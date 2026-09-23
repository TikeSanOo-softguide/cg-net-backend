<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wallet_transaction_id', 'from_wallet_id', 'to_wallet_id', 'amount', 'note'])]
class WalletTransfer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'wallet_transfers';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }
}
