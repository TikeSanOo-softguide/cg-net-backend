<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
