<?php

namespace App\Enums;

enum BillPaymentStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
