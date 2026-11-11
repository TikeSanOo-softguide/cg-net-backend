<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FtthBillPaymentHistoryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->billPayment;
        $snapshot = is_array($payment->snapshot) ? $payment->snapshot : [];
        $messageCode = $payment->historyMessageCode()?->value;

        return [
            'id' => $payment->id,
            'transaction_no' => $this->transaction_no,
            'type' => $this->type->value,
            'order_no' => $payment->order_no,
            'account' => $payment->broadband_account_number,
            'service_type' => $payment->service_type,
            'amount' => $this->amount,
            'package' => [
                'product_id' => $payment->product_id ?? ($snapshot['product_id'] ?? null),
                'product_name' => $snapshot['product_name'] ?? null,
                'product_price' => $payment->product_price ?? ($snapshot['product_price'] ?? null),
                'product_line' => $snapshot['product_line'] ?? null,
                'product_speed' => $snapshot['product_speed'] ?? null,
                'month_cycle' => $snapshot['month_cycle'] ?? null,
                'month_cycle_unit' => $snapshot['month_cycle_unit'] ?? null,
            ],
            'remark' => $payment->remark,
            'status' => $payment->status->value,
            'title_code' => $messageCode,
            'description_code' => $messageCode,
            'created_at' => $payment->created_at?->toIso8601String()
                ?? $this->created_at?->toIso8601String(),
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
        ];
    }
}
