<?php

namespace App\Enums;

enum PackageOrderStatus: string
{
    case Processing = 'processing';
    case PendingVerification = 'pending_verification';
    case Completed = 'completed';
    case Failed = 'failed';
}
