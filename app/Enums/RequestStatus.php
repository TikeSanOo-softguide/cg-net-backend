<?php

namespace App\Enums;

enum RequestStatus: string
{
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
