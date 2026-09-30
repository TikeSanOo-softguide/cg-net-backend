<?php

namespace App\Enums;

enum LedgerTransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
