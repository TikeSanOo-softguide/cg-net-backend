<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Topup = 'topup';
    case Transfer = 'transfer';
    case FtthBill = 'ftth_bill';
    case WifiPackage = 'wifi_package';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
