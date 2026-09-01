<?php

namespace App\Enums;

enum TopUpCardStatus: string
{
    case Valid = 'valid';
    case Redeemed = 'redeemed';
    case Invalid = 'invalid';
}
