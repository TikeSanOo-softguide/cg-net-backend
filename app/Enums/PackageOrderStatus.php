<?php

namespace App\Enums;

enum PackageOrderStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
