import { useState } from 'react';
import { router } from '@inertiajs/react';
import { BanIcon } from 'lucide-react';
import { DataTable } from '@/components/DataTable';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
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

    return (
        <>
            <DataTable
                data={batchRows}
                pagination={pagination}
                getRowId={(row) => String(row.id)}
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
                    can('top-up-cards.update') && row.status === 'active' ? (
                        <TableActionButton
                            label={t('top_up_cards.block_batch')}
                            icon={BanIcon}
                            tone="danger"
                            onClick={(event) => {
                                event.stopPropagation();
                                setVoiding(row);
                            }}
                        />
                    ) : null
                }
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
                onConfirm={() => {
                    if (!voiding) {
                        return;
                    }

                    router.patch(
                        `/top-up-cards/batches/${voiding.id}/void`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setVoiding(null),
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
