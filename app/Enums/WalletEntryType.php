<?php

namespace App\Enums;

enum WalletEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
