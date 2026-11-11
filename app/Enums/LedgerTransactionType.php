<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case Topup = 'topup';
    case FtthBill = 'ftth_bill';
    case CgBill = 'cg_bill';
    case WifiPackage = 'wifi_package';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    public function isInternetBill(): bool
    {
        return $this === self::FtthBill || $this === self::CgBill;
    }

    public static function forInternetServiceType(string $serviceType): self
    {
        return strtoupper($serviceType) === 'CG' ? self::CgBill : self::FtthBill;
    }

    /**
     * @return list<self>
     */
    public static function internetBillTypes(): array
    {
        return [self::FtthBill, self::CgBill];
    }

    /**
     * @return list<string>
     */
    public static function internetBillValues(): array
    {
        return [self::FtthBill->value, self::CgBill->value];
    }
}
