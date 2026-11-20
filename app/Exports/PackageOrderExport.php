<?php

namespace App\Exports;

use App\Models\PackageOrder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PackageOrderExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Transaction No.',
            'Order No.',
            'Customer',
            'Customer Phone',
            'Product ID',
            'Product Name',
            'Product Line',
            'Product Speed',
            'Product Price',
            'Term Cycle',
            'Term Cycle Unit',
            'Installation Fee',
            'Amount',
            'Status',
            'Activation Result Code',
            'Failure Reason',
            'Created At',
            'Completed At',
        ];
    }

    public function map(mixed $order): array
    {
        /** @var PackageOrder $order */
        $transaction = $order->ledgerTransaction;

        return [
            $transaction?->transaction_no ?? '',
            $order->order_no ?? '',
            $order->user?->name ?? '',
            $order->user?->phone ?? '',
            $order->snapshot['product_id'] ?? ($order->product_id ?? ''),
            $order->snapshot['product_name'] ?? '',
            $order->snapshot['product_line'] ?? '',
            $order->snapshot['product_speed'] ?? '',
            $order->snapshot['product_price'] ?? ($order->product_price ?? ''),
            $order->snapshot['term_cycle'] ?? '',
            $order->snapshot['term_cycle_unit'] ?? '',
            $order->snapshot['installation_fee'] ?? '',
            $order->snapshot['price'] ?? ($transaction?->amount ?? '0.00'),
            $order->status?->value ?? '',
            $order->activation_result_code ?? '',
            $order->failure_reason ?? '',
            $order->created_at ?? '',
            $order->completed_at?->toDateTimeString() ?? '',
        ];
    }
}
