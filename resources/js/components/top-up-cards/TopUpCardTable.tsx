import { memo, useState } from 'react';
import { router } from '@inertiajs/react';
import { BanIcon, CircleDotIcon, EyeIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SpinnerOverlay } from '@/components/ui/spinner';
import { toast } from '@/hooks/use-toast';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, type TopUpCardFilters, type TopUpCardRow } from '@/lib/top-up-cards';
import { formatDate } from '@/lib/utils';
import { CopyValueButton } from '../CopyValueButton';

type TopUpCardTableProps = {
    cards: Paginated<TopUpCardRow>;
    amounts: number[];
    filters: TopUpCardFilters;
    search: string;
    generatedPins: Record<number, string>;
    loading?: boolean;
    onSearchChange: (value: string) => void;
    onFilter: (next: TopUpCardFilters) => void;
};

export const TopUpCardTable = memo(function TopUpCardTable({
    cards,
    amounts,
    filters,
    search,
    generatedPins,
    loading = false,
    onSearchChange,
    onFilter,
}: TopUpCardTableProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [voiding, setVoiding] = useState<TopUpCardRow | null>(null);
    const [viewing, setViewing] = useState<TopUpCardRow | null>(null);

    const copyPin = async (card: TopUpCardRow) => {
        const pin = generatedPins[card.id] ?? card.pin;

        if (!pin) {
            toast({ variant: 'error', title: t('toast.error'), description: t('top_up_cards.pin_unavailable') });

            return;
        }

        await navigator.clipboard.writeText(pin.replace(/\D/g, ''));
        toast({ variant: 'success', title: t('toast.success'), description: t('top_up_cards.copied_pin') });
    };

    return (
        <>
            <div className="relative">
                {loading ? <SpinnerOverlay className="rounded-[12px]" /> : null}
                <DataTable
                    data={cards.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('top_up_cards.search_placeholder')}
                    emptyLabel={t('top_up_cards.empty_table')}
                    sort={filters.sort}
                    direction={filters.direction}
                    pagination={cards}
                    onSort={(column) => {
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                        onFilter({ ...filters, sort: column, direction: nextDirection });
                    }}
                    filters={
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <FormControl icon={CircleDotIcon} compact className="w-full shrink-0 sm:w-40">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) =>
                                        onFilter({ ...filters, status: value === 'all' ? '' : value })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={t('common.status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        <SelectItem value="active">{t('status.active')}</SelectItem>
                                        <SelectItem value="used">{t('status.used')}</SelectItem>
                                        <SelectItem value="blocked">{t('status.blocked')}</SelectItem>
                                        <SelectItem value="expired">{t('status.expired')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormControl compact className="w-full shrink-0 sm:w-40">
                                <Select
                                    value={filters.amount || 'all'}
                                    onValueChange={(value) =>
                                        onFilter({ ...filters, amount: value === 'all' ? '' : value })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={t('top_up_cards.amount')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('common.all')}</SelectItem>
                                        {amounts.map((amount) => (
                                            <SelectItem key={amount} value={String(amount)}>
                                                {formatTopUpAmount(amount)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                        </div>
                    }
                    columns={[
                        {
                            id: 'serial_no',
                            header: t('top_up_cards.serial_no'),
                            mobile: 'title',
                            className: 'font-mono text-[12px]',
                            cell: (row) => row.serial_no,
                        },
                        {
                            id: 'amount',
                            header: t('top_up_cards.amount'),
                            mobile: 'meta',
                            cell: (row) => formatTopUpAmount(row.amount),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            cell: (row) => <StatusBadge status={row.status} />,
                        },
                        {
                            id: 'expires_at',
                            header: t('top_up_cards.expires_at'),
                            className: 'text-muted-foreground',
                            cell: (row) => formatDate(row.expires_at) ?? '—',
                        },

                        {
                            id: 'batch_no',
                            header: t('top_up_cards.batch_no'),
                            cell: (row) => row.batch_no ?? '—',
                        },
                    ]}
                    actions={(row) => (
                        <>
                            <TableActionButton
                                label={t('top_up_cards.redemption')}
                                icon={EyeIcon}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    setViewing(row);
                                }}
                            />
                            {can('top-up-cards.update') && row.status === 'active' ? (
                                <TableActionButton
                                    label={t('top_up_cards.void')}
                                    icon={BanIcon}
                                    tone="danger"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setVoiding(row);
                                    }}
                                />
                            ) : null}
                        </>
                    )}
                />
            </div>
            <ConfirmDialog
                open={voiding !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setVoiding(null);
                    }
                }}
                title={t('top_up_cards.void_title')}
                description={t('top_up_cards.void_description')}
                destructive
                confirmLabel={t('top_up_cards.void')}
                onConfirm={() => {
                    if (!voiding) {
                        return;
                    }

                    router.patch(
                        `/top-up-cards/${voiding.id}/void`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setVoiding(null),
                        },
                    );
                }}
            />

            <FormDialog
                open={viewing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewing(null);
                    }
                }}
                title={t('top_up_cards.detail')}
                description={t('top_up_cards.description')}
                icon={EyeIcon}
                size="xl"
            >
                {viewing ? (
                    <div className="px-6 py-5 space-y-6">
                        <div className="flex items-center justify-between rounded-lg bg-muted/50 p-4 border border-border/50">
                            <div>
                                <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    {t('top_up_cards.amount')}
                                </span>
                                <div className="text-2xl font-bold tracking-tight text-foreground mt-0.5">
                                    {formatTopUpAmount(viewing.amount)}
                                </div>
                            </div>
                            <div>
                                <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider block mb-1">
                                    {t('common.status')}
                                </span>
                                <StatusBadge status={viewing.status} />
                            </div>
                        </div>

                        <div className="space-y-4">
                            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-[13px]">
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.serial_no')}</dt>
                                    <dd className="font-mono font-medium text-foreground">{viewing.serial_no}</dd>
                                </div>
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.expires_at')}</dt>
                                    <dd className="font-medium text-foreground">
                                        {formatDate(viewing.expires_at) ?? '—'}
                                    </dd>
                                </div>
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.batch_no')}</dt>
                                    <dd className="font-medium text-foreground">{viewing.batch_no ?? '—'}</dd>
                                </div>
                            </dl>
                        </div>

                        <hr className="border-border/60" />

                        <div className="space-y-4">
                            <dl className="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-[13px]">
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.redeemed_by')}</dt>
                                    <dd className="font-medium text-foreground">{viewing.redeemed_by ?? '—'}</dd>
                                </div>
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.redeemed_at')}</dt>
                                    <dd className="font-medium text-foreground">
                                        {formatDate(viewing.redeemed_at) ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {viewing.transaction_id ? (
                            <>
                                <hr className="border-border/60" />

                                <div className="space-y-4">
                                    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 text-[13px] sm:grid-cols-3">
                                        <div className="space-y-1">
                                            <dt className="text-muted-foreground">
                                                {t('top_up_cards.transaction_no')}
                                            </dt>
                                            <dd className="font-medium text-foreground">
                                                {viewing.transaction_no ?? '—'}
                                                {viewing.transaction_no && (
                                                    <CopyValueButton
                                                        value={viewing.transaction_no}
                                                        label={t('top_up_cards.transaction_no')}
                                                    />
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </>
                        ) : null}
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
});
