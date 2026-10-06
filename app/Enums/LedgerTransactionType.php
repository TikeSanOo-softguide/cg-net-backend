<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case Topup = 'topup';
    case FtthBill = 'ftth_bill';
    case WifiPackage = 'wifi_package';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
