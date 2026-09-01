import { useState } from 'react';
import { router } from '@inertiajs/react';
import { BanIcon, CalendarIcon, CircleDotIcon, CopyIcon, EyeIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { FormDialog } from '@/components/FormDialog';
import type { Paginated } from '@/components/Pagination';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SpinnerOverlay } from '@/components/ui/spinner';
import { toast } from '@/hooks/use-toast';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, type TopUpCardFilters, type TopUpCardRow } from '@/lib/top-up-cards';

type TopUpCardTableProps = {
    cards: Paginated<TopUpCardRow>;
    amounts: string[];
    filters: TopUpCardFilters;
    search: string;
    generatedPins: Record<number, string>;
    loading?: boolean;
    onSearchChange: (value: string) => void;
    onFilter: (next: TopUpCardFilters) => void;
};

export function TopUpCardTable({
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

        if (! pin) {
            toast({ variant: 'error', title: t('toast.error'), description: t('top_up_cards.pin_unavailable') });

            return;
        }

        await navigator.clipboard.writeText(pin);
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
                                onValueChange={(value) => onFilter({ ...filters, status: value === 'all' ? '' : value })}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="valid">{t('status.valid')}</SelectItem>
                                    <SelectItem value="redeemed">{t('status.redeemed')}</SelectItem>
                                    <SelectItem value="invalid">{t('status.invalid')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
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
                        cell: (row) => formatTopUpAmount(row.amount),
                    },
                    {
                        id: 'status',
                        header: t('common.status'),
                        sortable: true,
                        mobile: 'badge',
                        cell: (row) => <StatusBadge status={row.status} />,
                    },
                    {
                        id: 'expires_at',
                        header: t('top_up_cards.expires_at'),
                        sortable: true,
                        className: 'text-muted-foreground',
                        cell: (row) => row.expires_at ?? '—',
                    },
                    {
                        id: 'redeemed_at',
                        header: t('top_up_cards.redeemed_at'),
                        sortable: true,
                        className: 'text-muted-foreground',
                        cell: (row) => row.redeemed_at ?? '—',
                    },
                    {
                        id: 'redeemed_by',
                        header: t('top_up_cards.redeemed_by'),
                        cell: (row) => row.redeemed_by ?? '—',
                    },
                ]}
                actions={(row) => (
                    <>
                        <TableActionButton
                            label={t('top_up_cards.copy_pin')}
                            icon={CopyIcon}
                            tone="neutral"
                            onClick={(event) => {
                                event.stopPropagation();
                                void copyPin(row);
                            }}
                        />
                        <TableActionButton
                            label={t('top_up_cards.redemption')}
                            icon={EyeIcon}
                            onClick={(event) => {
                                event.stopPropagation();
                                setViewing(row);
                            }}
                        />
                        {can('top-up-cards.update') && row.status === 'valid' ? (
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
                    if (! open) {
                        setVoiding(null);
                    }
                }}
                title={t('top_up_cards.void_title')}
                description={t('top_up_cards.void_description')}
                destructive
                confirmLabel={t('top_up_cards.void')}
                onConfirm={() => {
                    if (! voiding) {
                        return;
                    }

                    router.patch(`/top-up-cards/${voiding.id}/void`, {}, {
                        preserveScroll: true,
                        onFinish: () => setVoiding(null),
                    });
                }}
            />
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
                        <dl className="grid grid-cols-1 gap-3 text-[13px]">
                            <div>
                                <dt className="text-muted-foreground">{t('top_up_cards.amount')}</dt>
                                <dd className="font-medium">{formatTopUpAmount(viewing.amount)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">{t('common.status')}</dt>
                                <dd className="mt-1"><StatusBadge status={viewing.status} /></dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">{t('top_up_cards.redeemed_at')}</dt>
                                <dd>{viewing.redeemed_at ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">{t('top_up_cards.redeemed_by')}</dt>
                                <dd>{viewing.redeemed_by ?? '—'}</dd>
                            </div>
                        </dl>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
}
