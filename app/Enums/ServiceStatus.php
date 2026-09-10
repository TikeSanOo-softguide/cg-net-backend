<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
