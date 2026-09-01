import { useState } from 'react';
import { CalendarIcon, EyeIcon, UserRoundIcon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SpinnerOverlay } from '@/components/ui/spinner';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import {
    dateDaysAgo,
    formatTopUpAmount,
    formatTopUpDateTime,
    isoDate,
    startOfMonthDate,
    type RedeemHistoryFilters,
    type TopUpCardRow,
} from '@/lib/top-up-cards';
import { cn } from '@/lib/utils';

type TopUpCardRedeemTableProps = {
    cards: Paginated<TopUpCardRow>;
    amounts: string[];
    filters: RedeemHistoryFilters;
    search: string;
    loading?: boolean;
    onSearchChange: (value: string) => void;
    onFilter: (next: RedeemHistoryFilters) => void;
};

const RANGE_PRESETS = [
    { id: 'today', from: () => isoDate(), to: () => isoDate(), labelKey: 'top_up_cards.range_today' },
    { id: '7d', from: () => dateDaysAgo(7), to: () => isoDate(), labelKey: 'top_up_cards.range_7d' },
    { id: 'month', from: () => startOfMonthDate(), to: () => isoDate(), labelKey: 'top_up_cards.range_month' },
] as const;

export function TopUpCardRedeemTable({
    cards,
    amounts,
    filters,
    search,
    loading = false,
    onSearchChange,
    onFilter,
}: TopUpCardRedeemTableProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [viewing, setViewing] = useState<TopUpCardRow | null>(null);

    return (
        <>
            <div className="relative">
                {loading ? <SpinnerOverlay className="rounded-[12px]" /> : null}
                <DataTable
                    data={cards.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('top_up_cards.history_search_placeholder')}
                    emptyLabel={t('top_up_cards.history_empty_filtered')}
                    sort={filters.sort}
                    direction={filters.direction}
                    pagination={cards}
                    onSort={(column) => {
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                        onFilter({ ...filters, sort: column, direction: nextDirection });
                    }}
                    filters={
                        <div className="flex w-full flex-col gap-2">
                            <div className="flex flex-wrap gap-1.5">
                                {RANGE_PRESETS.map((preset) => {
                                    const from = preset.from();
                                    const to = preset.to();
                                    const active = filters.from === from && filters.to === to;

                                    return (
                                        <button
                                            key={preset.id}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => onFilter({ ...filters, from, to })}
                                            className={cn(
                                                'inline-flex h-6 items-center rounded-full border px-2.5 text-[11px] font-medium transition-colors',
                                                active
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border/80 text-muted-foreground hover:border-primary/40 hover:text-primary',
                                            )}
                                        >
                                            {t(preset.labelKey)}
                                        </button>
                                    );
                                })}
                            </div>
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                                <FormControl compact className="w-full shrink-0 sm:w-40">
                                    <Select
                                        value={filters.amount || 'all'}
                                        onValueChange={(value) => onFilter({ ...filters, amount: value === 'all' ? '' : value })}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder={t('top_up_cards.amount')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('common.all')}</SelectItem>
                                            {amounts.map((amount) => (
                                                <SelectItem key={amount} value={amount}>
                                                    {formatTopUpAmount(amount)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                    <Input
                                        type="date"
                                        value={filters.from}
                                        onChange={(event) => onFilter({ ...filters, from: event.target.value })}
                                    />
                                </FormControl>
                                <FormControl icon={CalendarIcon} compact className="w-full shrink-0 sm:w-40">
                                    <Input
                                        type="date"
                                        value={filters.to}
                                        onChange={(event) => onFilter({ ...filters, to: event.target.value })}
                                    />
                                </FormControl>
                            </div>
                        </div>
                    }
                    columns={[
                        {
                            id: 'serial_no',
                            header: t('top_up_cards.serial_no'),
                            sortable: true,
                            mobile: 'title',
                            className: 'font-mono text-[12px]',
                            cell: (row) => row.serial_no,
                        },
                        {
                            id: 'amount',
                            header: t('top_up_cards.amount'),
                            sortable: true,
                            mobile: 'meta',
                            cell: (row) => (
                                <span className="font-semibold tabular-nums text-primary">{formatTopUpAmount(row.amount)}</span>
                            ),
                        },
                        {
                            id: 'redeemed_by',
                            header: t('top_up_cards.customer'),
                            mobile: 'subtitle',
                            cell: (row) => (
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium">{row.redeemed_by ?? t('top_up_cards.no_customer')}</p>
                                    {row.redeemed_by_phone ? (
                                        <p className="truncate text-[11px] text-muted-foreground">{row.redeemed_by_phone}</p>
                                    ) : null}
                                </div>
                            ),
                        },
                        {
                            id: 'redeemed_at',
                            header: t('top_up_cards.redeemed_at'),
                            sortable: true,
                            className: 'text-muted-foreground',
                            cell: (row) => formatTopUpDateTime(row.redeemed_at),
                        },
                        {
                            id: 'status',
                            header: t('common.status'),
                            mobile: 'badge',
                            cell: (row) => <StatusBadge status={row.status} />,
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
                            {can('customers.view') && row.redeemed_by_id ? (
                                <TableActionButton
                                    label={t('top_up_cards.view_customer')}
                                    icon={UserRoundIcon}
                                    tone="neutral"
                                    href={`/customers/${row.redeemed_by_id}`}
                                />
                            ) : null}
                        </>
                    )}
                />
            </div>
            <FormDialog
                open={viewing !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        setViewing(null);
                    }
                }}
                title={viewing?.serial_no ?? t('top_up_cards.redemption')}
                description={t('top_up_cards.redemption_description')}
                icon={EyeIcon}
                size="sm"
            >
                {viewing ? (
                    <div className="px-4 py-4 sm:px-5">
                        <div className="relative overflow-hidden rounded-[10px] border border-primary/20 bg-[linear-gradient(180deg,hsl(var(--primary)/0.10),hsl(var(--card)))] p-3">
                            <span className="absolute inset-y-0 left-0 w-[3px] bg-primary/60" aria-hidden />
                            <dl className="grid grid-cols-1 gap-3 pl-1.5 text-[13px]">
                                <div>
                                    <dt className="text-[11px] text-muted-foreground">{t('top_up_cards.amount')}</dt>
                                    <dd className="font-heading text-lg font-bold tabular-nums text-primary">
                                        {formatTopUpAmount(viewing.amount)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] text-muted-foreground">{t('common.status')}</dt>
                                    <dd className="mt-1"><StatusBadge status={viewing.status} /></dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] text-muted-foreground">{t('top_up_cards.customer')}</dt>
                                    <dd className="font-medium">{viewing.redeemed_by ?? t('top_up_cards.no_customer')}</dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] text-muted-foreground">{t('top_up_cards.customer_phone')}</dt>
                                    <dd>{viewing.redeemed_by_phone ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-[11px] text-muted-foreground">{t('top_up_cards.redeemed_at')}</dt>
                                    <dd>{formatTopUpDateTime(viewing.redeemed_at)}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
}
