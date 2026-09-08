<?php

namespace App\Enums;

enum ChangePasswordStatus: string
{
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
