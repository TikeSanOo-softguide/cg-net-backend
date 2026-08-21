<?php

namespace App\Enums;

enum NewsStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
