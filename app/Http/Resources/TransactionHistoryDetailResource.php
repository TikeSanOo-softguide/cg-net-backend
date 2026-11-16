<?php

namespace App\Http\Resources;

use App\Enums\LedgerTransactionType;
use App\Models\BillPayment;
use App\Models\PackageOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $walletEntry = $this->entries->firstWhere('wallet_id', $this->wallet_id);
        $messageCode = $this->historyMessageCode()?->value;
        $bill = $this->type->isInternetBill() ? $this->billPayment : null;
        $order = $this->type === LedgerTransactionType::WifiPackage ? $this->packageOrder : null;

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
            'title_code' => $messageCode,
            'description_code' => $messageCode,
            'account' => $bill?->broadband_account_number,
            'service_type' => $bill?->service_type,
            'order_no' => $bill?->order_no ?? $order?->order_no,
            'remark' => $bill?->remark,
            'package' => $this->packagePayload($bill, $order),
            'serial_masked' => $this->type === LedgerTransactionType::Topup
                ? $this->maskedSerial($this->topUpCard?->serial_no)
                : null,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $bill?->confirmed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packagePayload(?BillPayment $bill, ?PackageOrder $order): ?array
    {
        $snapshot = is_array($bill?->snapshot)
            ? $bill->snapshot
            : (is_array($order?->snapshot) ? $order->snapshot : null);

        if ($bill === null && $order === null) {
            return null;
        }

        $snapshot ??= [];

        return [
            'product_id' => $bill?->product_id ?? $order?->product_id ?? ($snapshot['product_id'] ?? null),
            'product_name' => $snapshot['product_name'] ?? null,
            'product_price' => $bill?->product_price ?? $order?->product_price ?? ($snapshot['product_price'] ?? null),
            'product_line' => $snapshot['product_line'] ?? null,
            'product_speed' => $snapshot['product_speed'] ?? null,
            'month_cycle' => $snapshot['month_cycle'] ?? ($snapshot['term_cycle'] ?? ($snapshot['term_months'] ?? null)),
            'month_cycle_unit' => $snapshot['month_cycle_unit'] ?? ($snapshot['term_cycle_unit'] ?? null),
        ];
    }

    private function maskedSerial(?string $serial): ?string
    {
        $serial = trim((string) $serial);
        if ($serial === '') {
            return null;
        }
        if (strlen($serial) < 8) {
            return '****';
        }

        return substr($serial, 0, 4).'****'.substr($serial, -4);
    }
}
