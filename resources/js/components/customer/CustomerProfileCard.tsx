import type { LucideIcon } from 'lucide-react';
import {
    BanIcon,
    CalendarIcon,
    PhoneIcon,
    SquarePenIcon,
    UserCheckIcon,
    WalletIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { PhoneDisplay } from '@/components/customer/PhoneDisplay';
import { StaffListAvatar } from '@/components/staff/StaffListAvatar';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type FactProps = {
    icon: LucideIcon;
    label: string;
    children: ReactNode;
    className?: string;
};

function Fact({ icon: Icon, label, children, className }: FactProps) {
    return (
        <div className={cn('min-w-0 rounded-[8px] border border-border/60 bg-muted/25 px-3 py-2.5', className)}>
            <div className="mb-1 flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <Icon className="size-3 opacity-70" strokeWidth={1.85} />
                <span className="truncate">{label}</span>
            </div>
            <div className="min-w-0 text-[13px] font-medium leading-snug text-foreground">{children}</div>
        </div>
    );
}

type CustomerProfileCardProps = {
    name: string;
    phone: string;
    status: string;
    joined: string | null;
    walletBalance: string;
    onEdit: () => void;
    onToggleStatus: () => void;
};

export function CustomerProfileCard({
    name,
    phone,
    status,
    joined,
    walletBalance,
    onEdit,
    onToggleStatus,
}: CustomerProfileCardProps) {
    const { t } = useTranslation();
    const isActive = status === 'active';

    return (
        <section
            className={cn(
                'overflow-hidden border border-border/80 bg-[#FFFFFF]',
                'shadow-[0_8px_24px_rgb(23_50_54/0.08),0_20px_48px_rgb(23_50_54/0.12)]',
                'rounded-none sm:rounded-[12px]',
                'dark:bg-card dark:shadow-[0_8px_24px_rgb(0_0_0/0.28),0_20px_48px_rgb(0_0_0/0.32)]',
            )}
        >
            <div className="flex flex-col gap-4 border-b border-border/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <StaffListAvatar username={name} className="size-12 text-sm" />
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="truncate text-[15px] font-semibold leading-tight text-foreground">{name}</h2>
                            <StatusBadge status={status} />
                        </div>
                        <div className="mt-1.5 flex min-w-0 items-center gap-1.5 text-[12px] text-muted-foreground">
                            <PhoneIcon className="size-3 shrink-0 opacity-70" strokeWidth={1.85} />
                            <PhoneDisplay phone={phone} />
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8 gap-1 rounded-[6px] px-2.5 text-[11px]"
                        onClick={onEdit}
                    >
                        <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                        {t('common.edit')}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={isActive ? 'destructive' : 'primary'}
                        className="h-8 gap-1 rounded-[6px] px-2.5 text-[11px]"
                        onClick={onToggleStatus}
                    >
                        {isActive ? (
                            <BanIcon className="size-3.5" strokeWidth={1.85} />
                        ) : (
                            <UserCheckIcon className="size-3.5" strokeWidth={1.85} />
                        )}
                        {isActive ? t('customers.suspend') : t('customers.reactivate')}
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-2.5 p-3 sm:grid-cols-3 sm:p-4">
                <Fact icon={WalletIcon} label={t('customers.wallet_balance')} className="sm:col-span-1">
                    <span className="font-heading text-[15px] font-semibold tabular-nums text-primary">
                        {walletBalance}
                    </span>
                </Fact>
                <Fact icon={PhoneIcon} label={t('customers.phone')}>
                    <PhoneDisplay phone={phone} />
                </Fact>
                <Fact icon={CalendarIcon} label={t('customers.joined')}>
                    <span className="font-mono text-[12px] tabular-nums text-muted-foreground">{joined ?? '—'}</span>
                </Fact>
            </div>
        </section>
    );
}
