<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $walletEntry = $this->entries->firstWhere('wallet_id', $this->wallet_id);

        return [
            'id' => $this->id,
            'transaction_no' => $this->transaction_no,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'note' => $this->note,
            'direction' => $walletEntry
                ? ($walletEntry->credit > 0 ? 'credit' : 'debit')
                : null,
            'balance_before' => $walletEntry?->balance_before,
            'balance_after' => $walletEntry?->balance_after,
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
