<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FtthBillPaymentHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->billPayment;
        $messageCode = $payment->historyMessageCode()?->value;

        return [
            'id' => $payment->id,
            'transaction_no' => $this->transaction_no,
            'type' => $this->type->value,
            'account' => $payment->broadband_account_number,
            'service_type' => $payment->service_type,
            'amount' => $this->amount,
            'status' => $payment->status->value,
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
            'title_code' => $messageCode,
            'description_code' => $messageCode,
        ];
    }
}
