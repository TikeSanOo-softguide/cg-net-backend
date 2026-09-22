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

#[
    Fillable([
        'serial_no',
        'pin',
        'pin_lookup',
        'amount',
        'expires_at',
        'redeemed_at',
        'redeemed_by',
        'status',
        'agent_id',
        'batch_id',
        'wallet_transaction_id',
    ]),
]
#[Hidden(['pin', 'pin_lookup'])]
class TopUpCard extends Model
{
    /** @use HasFactory<TopUpCardFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'top_up_card';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'date',
            'redeemed_at' => 'datetime',
            'status' => TopUpCardStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
