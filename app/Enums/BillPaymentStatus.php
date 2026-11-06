<?php

namespace App\Enums;

enum BillPaymentStatus: string
{
    case Processing = 'processing';
    case Review = 'review';
    case Completed = 'completed';
    case Failed = 'failed';
}
