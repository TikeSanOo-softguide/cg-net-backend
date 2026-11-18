<?php

namespace App\Enums;

enum TransactionIdPrefix: string
{
    case PayBillFtth = 'PBFT';
    case PayBillCg = 'PBCG';
    case BuyPackage = 'PKG';
    case TopUp = 'TOP';
    case Refund = 'REF';
    case Adjustment = 'ADJ';

    public function randomLength(): int
    {
        return 16 - strlen($this->value) - 6;
    }

    public static function forType(LedgerTransactionType $type): self
    {
        return match ($type) {
            LedgerTransactionType::WifiPackage => self::BuyPackage,
            LedgerTransactionType::FtthBill => self::PayBillFtth,
            LedgerTransactionType::CgBill => self::PayBillCg,
            LedgerTransactionType::Topup => self::TopUp,
            LedgerTransactionType::Refund => self::Refund,
            LedgerTransactionType::Adjustment => self::Adjustment,
        };
    }

    public static function forPayBill(string $serviceType): self
    {
        return strtoupper($serviceType) === 'CG' ? self::PayBillCg : self::PayBillFtth;
    }
}
