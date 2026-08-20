<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Kbzpay = 'kbzpay';
    case Wave = 'wave';
}
