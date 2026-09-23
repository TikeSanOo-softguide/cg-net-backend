<?php

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
