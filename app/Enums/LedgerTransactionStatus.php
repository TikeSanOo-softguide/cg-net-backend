<?php

namespace App\Enums;

enum LedgerTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Review = 'review';
    case Completed = 'completed';
    case Failed = 'failed';
}
