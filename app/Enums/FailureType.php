<?php

namespace App\Enums;

enum FailureType: string
{
    case NoInternet = 'no_internet';
    case Slow = 'slow';
    case Unstable = 'unstable';
    case Password = 'password';
    case Equipment = 'equipment';
    case Other = 'other';
}
