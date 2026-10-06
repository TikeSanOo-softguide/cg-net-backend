<?php

namespace App\Models;

use App\Enums\BillPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[
    Fillable([
        'ledger_transaction_id',
        'broadband_account_number',
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

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('BillPayment records cannot be deleted.');
        });
    }

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

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
