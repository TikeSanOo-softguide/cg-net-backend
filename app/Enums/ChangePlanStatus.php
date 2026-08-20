<?php

namespace App\Enums;

enum ChangePlanStatus: string
{
    case UnderReview = 'under_review';
    case Approved = 'approved';
}
