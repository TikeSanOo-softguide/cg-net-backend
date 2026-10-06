<?php

namespace App\Enums;

enum LedgerAccountCode: string
{
    case CashTopup = 'CASH_TOPUP';
    case FtthClearing = 'FTTH_CLEARING';
    case PackageRevenue = 'PKG_REVENUE';
    case AdjustmentExpense = 'ADJ_EXPENSE';

    public function accountType(): LedgerAccountType
    {
        return match ($this) {
            self::CashTopup, self::FtthClearing => LedgerAccountType::Asset,
            self::PackageRevenue => LedgerAccountType::Revenue,
            self::AdjustmentExpense => LedgerAccountType::Expense,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CashTopup => 'Top-up clearing',
            self::FtthClearing => 'FTTH billing clearing',
            self::PackageRevenue => 'Package revenue',
            self::AdjustmentExpense => 'Wallet adjustments',
        };
    }
}
