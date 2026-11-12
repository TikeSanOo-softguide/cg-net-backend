<?php

namespace App\Enums;

enum LedgerAccountCode: string
{
    case CashTopup = 'CASH_TOPUP';
    case InternetServiceRevenue = 'INET_SVC_REVENUE';
    case PackageRevenue = 'PKG_REVENUE';
    case AdjustmentExpense = 'ADJ_EXPENSE';

    public function accountType(): LedgerAccountType
    {
        return match ($this) {
            self::CashTopup => LedgerAccountType::Asset,
            self::InternetServiceRevenue, self::PackageRevenue => LedgerAccountType::Revenue,
            self::AdjustmentExpense => LedgerAccountType::Expense,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CashTopup => 'Top-up clearing',
            self::InternetServiceRevenue => 'Internet service revenue',
            self::PackageRevenue => 'Package revenue',
            self::AdjustmentExpense => 'Wallet adjustments',
        };
    }
}
