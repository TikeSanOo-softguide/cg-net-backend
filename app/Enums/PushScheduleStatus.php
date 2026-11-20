<?php

namespace App\Enums;

enum PushScheduleStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case NoRecipients = 'no_recipients';
    case Cancelled = 'cancelled';
}
