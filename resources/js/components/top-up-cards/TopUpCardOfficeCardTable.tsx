import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import type { Paginated } from '@/components/Pagination';
import { formatDate } from '@/lib/utils';

type BatchTableRow = { id: number; batch_no: string; total_value: number; quantity: number; status: string; expires_at: string | null; assigned_cards_count: number };

type TopUpCardOfficeCardTableProps = {
    batchRows: BatchTableRow[];
    pagination?: Paginated<BatchTableRow>;
    batches: { id: number; batch_no: string }[];
    batchFilter: string;
    statusFilter: string;
    onBatchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onImport: () => void;
};

export function TopUpCardOfficeCardTable({
    batchRows,
    pagination,
    batches,
    batchFilter,
    statusFilter,
    onBatchChange,
    onStatusChange,
    onImport,
}: TopUpCardOfficeCardTableProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <DataTable
            data={batchRows}
            pagination={pagination}
            getRowId={(row) => String(row.id)}
            showSearch={false}
            emptyLabel={t('top_up_cards.empty_table')}
            filters={
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <FormControl compact className="w-full shrink-0 sm:w-48">
                        <Select value={batchFilter || 'all'} onValueChange={onBatchChange}>
                            <SelectTrigger className="w-full"><SelectValue placeholder={t('top_up_cards.batch_no')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('top_up_cards.office.all_batches')}</SelectItem>
                                {batches.map((batch) => <SelectItem key={batch.id} value={String(batch.id)}>{batch.batch_no}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </FormControl>
                    <FormControl compact className="w-full shrink-0 sm:w-35">
                        <Select value={statusFilter || 'all'} onValueChange={onStatusChange}>
                            <SelectTrigger className="w-full"><SelectValue placeholder={t('common.status')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('top_up_cards.office.all_status')}</SelectItem>
                                <SelectItem value="active">{t('status.active')}</SelectItem>
                                <SelectItem value="used">{t('status.used')}</SelectItem>
                                <SelectItem value="expired">{t('status.expired')}</SelectItem>
                                <SelectItem value="blocked">{t('status.blocked')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormControl>
                </div>
            }
            alwaysShowBulkActions
            bulkActions={can('top-up-cards.update') ? (
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={onImport}>
                        {t('top_up_cards.office.import_csv')}
                    </Button>
                </div>
            ) : null}
            columns={[
                { id: 'batch_no', header: t('top_up_cards.batch_no'), mobile: 'title', className: 'font-mono text-[12px]', cell: (row) => row.batch_no },
                { id: 'quantity', header: t('top_up_cards.quantity'), mobile: 'meta', cell: (row) => row.quantity.toLocaleString() },
                { id: 'total_value', header: t('top_up_cards.total_value'), cell: (row) => row.total_value.toLocaleString() },
                { id: 'status', header: t('common.status'), mobile: 'badge', cell: (row) => <StatusBadge status={row.status} /> },
                { id: 'expires_at', header: t('top_up_cards.expires_at'), cell: (row) => formatDate(row.expires_at) ?? '—' },
                { id: 'assigned_cards_count', header: t('top_up_cards.office.assigned_cards'), cell: (row) => row.assigned_cards_count.toLocaleString() },
            ]}
        />
    );
}
