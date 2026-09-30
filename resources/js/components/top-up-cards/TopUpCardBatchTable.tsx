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
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, type BatchFilters, type BatchRow } from '@/lib/top-up-cards';
import { formatDate } from '@/lib/utils';
import { FormField } from '../ui/form-field';

type BatchProps = {
    batches: Paginated<BatchRow>;
    amounts: string[];
    filters: BatchFilters;
    search: string;
    generatedPins: Record<number, string>;
    loading?: boolean;
    onSearchChange: (value: string) => void;
    onFilter: (next: BatchFilters) => void;
};

export const TopUpCardBatchTable = memo(function TopUpCardBatchTable({
    batches,
    amounts,
    filters,
    search,
    generatedPins,
    loading = false,
    onSearchChange,
    onFilter,
}: BatchProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [cancel, setCancel] = useState<BatchRow | null>(null);
    const [viewing, setViewing] = useState<BatchRow | null>(null);

    return (
        <>
            <div className="relative">
                {loading ? <SpinnerOverlay className="rounded-[12px]" /> : null}
                <DataTable
                    data={batches.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={onSearchChange}
                    searchPlaceholder={t('top_up_cards.search_placeholder')}
                    emptyLabel={t('top_up_cards.empty_table')}
                    sort={filters.sort}
                    direction={filters.direction}
                    pagination={batches}
                    onSort={(column) => {
                        const nextDirection = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
                        onFilter({ ...filters, sort: column, direction: nextDirection });
                    }}
                    filters={
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <FormField
                                label={t('common.status')}
                                htmlFor="status"
                                icon={CircleDotIcon}
                                className="w-full shrink-0 sm:w-40 mr-3"
                                labelClassName="text-[13px]"
                            >
                                {' '}
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
                                        <SelectItem value="cancelled">{t('status.cancelled')}</SelectItem>
                                        <SelectItem value="expired">{t('status.expired')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                    }
                    columns={[
                        {
                            id: 'batch_no',
                            header: t('top_up_cards.batch_no'),
                            cell: (row) => row.batch_no ?? '—',
                        },
                        {
                            id: 'quantity',
                            header: t('top_up_cards.quantity'),
                            mobile: 'meta',
                            cell: (row) => row.quantity,
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
                                    label={t('top_up_cards.cancel')}
                                    icon={BanIcon}
                                    tone="danger"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        setCancel(row);
                                    }}
                                />
                            ) : null}
                        </>
                    )}
                />
            </div>
            <ConfirmDialog
                open={cancel !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCancel(null);
                    }
                }}
                title={t('top_up_cards.batch.cancel_title')}
                description={t('top_up_cards.batch.cancel_description')}
                destructive
                confirmLabel={t('top_up_cards.batch.cancel')}
                onConfirm={() => {
                    if (!cancel) {
                        return;
                    }

                    router.patch(
                        `/top-up-cards/batch/${cancel.id}/cancel`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setCancel(null),
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
                title={t('top_up_cards.batch.detail')}
                description={t('top_up_cards.batch.description')}
                icon={EyeIcon}
                size="xl"
            >
                {viewing ? (
                    <div className="px-6 py-5 space-y-6">
                        <div className="flex items-center justify-between rounded-lg bg-muted/50 p-4 border border-border/50">
                            <div>
                                <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                    {t('top_up_cards.batch_no')}
                                </span>
                                <div className="text-2xl font-bold tracking-tight text-foreground mt-0.5">
                                    <dd className="font-mono font-medium text-foreground">{viewing.batch_no}</dd>
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
                                    <dt className="text-muted-foreground">{t('top_up_cards.batch.total_value')}</dt>
                                    {formatTopUpAmount(viewing.total_value)}
                                </div>
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.expires_at')}</dt>
                                    <dd className="font-medium text-foreground">
                                        {formatDate(viewing.expires_at) ?? '—'}
                                    </dd>
                                </div>
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">{t('top_up_cards.quantity')}</dt>
                                    <dd className="font-medium text-foreground">{viewing.quantity ?? '—'}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                ) : null}
            </FormDialog>
        </>
    );
});
