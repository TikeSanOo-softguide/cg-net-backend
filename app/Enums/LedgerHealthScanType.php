<?php

namespace App\Enums;

enum LedgerHealthScanType: string
{
    case Full = 'full';
    case Daily = 'daily';
    case Manual = 'manual';

    public function usesWindow(): bool
    {
        return $this === self::Daily || $this === self::Manual;
    }
}
