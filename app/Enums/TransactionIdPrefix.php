<?php

namespace App\Enums;

enum TransactionIdPrefix: string
{
    case PayBillFtth = 'PBFT';
    case PayBillCg = 'PBCG';
    case BuyPackage = 'PKG';
    case TopUp = 'TOP';

    public function randomLength(): int
    {
        return 16 - strlen($this->value) - 6;
    }

    public static function forPayBill(string $serviceType): self
    {
        return strtoupper($serviceType) === 'CG' ? self::PayBillCg : self::PayBillFtth;
    }
}
