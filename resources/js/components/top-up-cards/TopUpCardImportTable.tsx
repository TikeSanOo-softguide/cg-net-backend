import { useState } from 'react';
import { router } from '@inertiajs/react';
import { BanIcon, CheckCircleIcon, LoaderCircleIcon, MoreVerticalIcon } from 'lucide-react';
import { DataTable } from '@/components/DataTable';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatusBadge } from '@/components/StatusBadge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import type { Paginated } from '@/components/Pagination';
import { formatDate } from '@/lib/utils';
import { TopUpCardImportDialog, type TopUpCardCsvPreview } from './TopUpCardImportDialog';
import { FormField } from '../ui/form-field';
import { SearchableSelect } from '../SearchableSelect';

type BatchTableRow = {
    id: number;
    batch_no: string;
    total_value: number;
    quantity: number;
    status: string;
    expires_at: string | null;
    assigned_cards_count: number;
};

type TopUpCardImportTableProps = {
    batchRows: BatchTableRow[];
    pagination?: Paginated<BatchTableRow>;
    batches: {
        id: number;
        batch_no: string;
    }[];
    batchFilter: string;
    statusFilter: string;
    onBatchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onCheckImport: (file: File) => Promise<TopUpCardCsvPreview>;
    onImport: (file: File) => void;
    importProcessing?: boolean;
};

export function TopUpCardImportTable({
    batchRows,
    pagination,
    batches,
    batchFilter,
    statusFilter,
    onBatchChange,
    onStatusChange,
    onCheckImport,
    onImport,
    importProcessing = false,
}: TopUpCardImportTableProps) {
    const { t } = useTranslation();
    const can = useCan();

    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [voiding, setVoiding] = useState<BatchTableRow | null>(null);
    const [activating, setActivating] = useState<BatchTableRow | null>(null);
    const [activationProcessing, setActivationProcessing] = useState(false);
    const [blockProcessing, setBlockProcessing] = useState(false);

    return (
        <>
            <DataTable
                data={batchRows}
                pagination={pagination}
                getRowId={(row) => String(row.id)}
                directActions
                showSearch={false}
                emptyLabel={t('top_up_cards.empty_table')}
                filters={
                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <FormField
                            label={t('top_up_cards.batch_no')}
                            htmlFor="batch-filter"
                            className="w-full shrink-0 sm:w-50"
                            labelClassName="text-[13px]"
                        >
                            <SearchableSelect
                                id="batch-filter"
                                value={batchFilter || 'all'}
                                onValueChange={onBatchChange}
                                options={[
                                    { value: 'all', label: t('top_up_cards.office.all_batches') as string },
                                    ...batches.map((batch) => ({ value: String(batch.id), label: batch.batch_no })),
                                ]}
                                placeholder={t('top_up_cards.batch_no') as string}
                                searchPlaceholder={t('top_up_cards.batch_no') as string}
                                className="w-full text-xs"
                            />
                        </FormField>
                        <FormField
                            label={t('common.status')}
                            htmlFor="batch-status"
                            className="w-full shrink-0 sm:w-40 mr-3"
                            labelClassName="text-[13px]"
                        >
                            <Select value={statusFilter || 'all'} onValueChange={onStatusChange}>
                                <SelectTrigger id="batch-status" className="w-full">
                                    <SelectValue placeholder={t('common.status')} />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="all">{t('common.all')}</SelectItem>
                                    <SelectItem value="pending">{t('status.pending')}</SelectItem>
                                    <SelectItem value="active">{t('status.active')}</SelectItem>
                                    <SelectItem value="expired">{t('status.expired')}</SelectItem>
                                    <SelectItem value="blocked">{t('status.blocked')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>
                }
                alwaysShowBulkActions
                bulkActions={
                    can('top-up-cards.create') ? (
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="bg-primary text-white hover:bg-sidebar-item-hover hover:text-white [&_svg]:text-white"
                                disabled={importProcessing}
                                onClick={() => setImportDialogOpen(true)}
                            >
                                {t('top_up_cards.import_csv.label')}
                            </Button>
                        </div>
                    ) : null
                }
                columns={[
                    {
                        id: 'batch_no',
                        header: t('top_up_cards.batch_no'),
                        mobile: 'title',
                        className: 'font-mono text-[12px]',
                        cell: (row) => row.batch_no,
                    },
                    {
                        id: 'quantity',
                        header: t('top_up_cards.quantity'),
                        mobile: 'meta',
                        cell: (row) => row.quantity.toLocaleString(),
                    },
                    {
                        id: 'total_value',
                        header: t('top_up_cards.total_value'),
                        cell: (row) => row.total_value.toLocaleString(),
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
                        cell: (row) => formatDate(row.expires_at) ?? '—',
                    },
                ]}
                actions={(row) =>
                    !can('top-up-cards.update') ? null : row.status === 'pending' ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="whitespace-nowrap"
                            disabled={activationProcessing && activating?.id === row.id}
                            onClick={(event) => {
                                event.stopPropagation();
                                setActivating(row);
                            }}
                        >
                            {activationProcessing && activating?.id === row.id ? (
                                <LoaderCircleIcon className="animate-spin" />
                            ) : (
                                <CheckCircleIcon />
                            )}
                            {activationProcessing && activating?.id === row.id
                                ? t('top_up_cards.activating_batch')
                                : t('top_up_cards.activate_batch')}
                        </Button>
                    ) : row.status === 'active' ? (
                        blockProcessing && voiding?.id === row.id ? (
                            <Button type="button" variant="outline" size="sm" className="whitespace-nowrap" disabled>
                                <LoaderCircleIcon className="animate-spin" />
                                {t('top_up_cards.blocking_batch')}
                            </Button>
                        ) : (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        aria-label={t('common.actions')}
                                        aria-haspopup="menu"
                                        className="group relative z-[2] inline-flex size-7 items-center justify-center rounded-[6px] bg-primary/12 text-primary leading-none transition-all duration-200 ease-out hover:scale-105 hover:bg-primary hover:text-primary-foreground hover:shadow-sm active:scale-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none data-[state=open]:bg-primary data-[state=open]:text-primary-foreground data-[state=open]:shadow-sm"
                                        onClick={(event) => event.stopPropagation()}
                                    >
                                        <MoreVerticalIcon
                                            className="size-3.5 transition-transform duration-200 group-data-[state=open]:rotate-90"
                                            strokeWidth={1.85}
                                        />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent side="top" align="center">
                                    <DropdownMenuItem variant="destructive" onSelect={() => setVoiding(row)}>
                                        <BanIcon className="size-4" />
                                        {t('top_up_cards.block_batch')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )
                    ) : null
                }
            />

            <ConfirmDialog
                open={activating !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActivating(null);
                    }
                }}
                title={t('top_up_cards.activate_batch_title')}
                description={t('top_up_cards.activate_batch_description')}
                confirmLabel={t('top_up_cards.activate_batch')}
                processing={activationProcessing}
                onConfirm={() => {
                    if (!activating) {
                        return;
                    }

                    router.patch(
                        `/top-up-cards/batches/${activating.id}/activate`,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setActivationProcessing(true),
                            onFinish: () => {
                                setActivationProcessing(false);
                                setActivating(null);
                            },
                        },
                    );
                }}
            />

            <ConfirmDialog
                open={voiding !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setVoiding(null);
                    }
                }}
                title={t('top_up_cards.block_batch_title')}
                description={t('top_up_cards.block_batch_description')}
                destructive
                confirmLabel={t('top_up_cards.void')}
                processing={blockProcessing}
                onConfirm={() => {
                    if (!voiding) {
                        return;
                    }

                    router.patch(
                        `/top-up-cards/batches/${voiding.id}/void`,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setBlockProcessing(true),
                            onFinish: () => {
                                setBlockProcessing(false);
                                setVoiding(null);
                            },
                        },
                    );
                }}
            />

            <TopUpCardImportDialog
                open={importDialogOpen}
                onOpenChange={setImportDialogOpen}
                onCheck={onCheckImport}
                onImport={onImport}
            />
        </>
    );
}
