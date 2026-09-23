<?php

namespace App\Models;

use App\Enums\WalletEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wallet_transaction_id', 'wallet_id', 'amount', 'balance_before', 'balance_after', 'type'])]
class WalletEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'wallet_entries';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'type' => WalletEntryType::class,
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
