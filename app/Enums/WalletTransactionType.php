<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Redeem = 'redeem';
    case Topup = 'topup';
}
