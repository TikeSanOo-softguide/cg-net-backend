<?php

namespace App\Models;

use App\Enums\BillPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[
    Fillable([
        'wallet_transaction_id',
        'broadband_account_id',
        'status',
        'external_bill_ref',
        'external_payment_ref',
        'external_response',
        'confirmed_at',
    ]),
]
class BillPayment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'bill_payments';

    protected function casts(): array
    {
        return [
            'external_response' => 'array',
            'confirmed_at' => 'datetime',
            'status' => BillPaymentStatus::class,
        ];
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function broadbandAccount(): BelongsTo
    {
        return $this->belongsTo(BroadbandAccount::class);
    }
}
