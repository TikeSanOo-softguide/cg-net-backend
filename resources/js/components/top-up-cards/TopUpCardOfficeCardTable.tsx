import { DataTable } from '@/components/DataTable';
import { StatusBadge } from '@/components/StatusBadge';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, type TopUpCardRow } from '@/lib/top-up-cards';
import type { Paginated } from '@/components/Pagination';
import { formatDate } from '@/lib/utils';

type OfficeRow = { id: number; name: string; address: string; top_up_cards_count: number };

type TopUpCardOfficeCardTableProps = {
    cards: TopUpCardRow[];
    pagination?: Paginated<TopUpCardRow>;
    offices: OfficeRow[];
    batches: { id: number; batch_no: string }[];
    points: number[];
    search: string;
    batchFilter: string;
    officeFilter: string;
    statusFilter: string;
    pointFilter: string;
    onSearchChange: (value: string) => void;
    onBatchChange: (value: string) => void;
    onOfficeChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onPointChange: (value: string) => void;
    onAssign: () => void;
    onImport: () => void;
};

export function TopUpCardOfficeCardTable({
    cards,
    pagination,
    offices,
    batches,
    points,
    search,
    batchFilter,
    officeFilter,
    statusFilter,
    pointFilter,
    onSearchChange,
    onBatchChange,
    onOfficeChange,
    onStatusChange,
    onPointChange,
    onAssign,
    onImport,
}: TopUpCardOfficeCardTableProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <DataTable
            data={cards}
            pagination={pagination}
            getRowId={(row) => String(row.id)}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder={t('top_up_cards.search_placeholder')}
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
                    <FormControl compact className="w-full shrink-0 sm:w-48">
                        <Select value={officeFilter || 'all'} onValueChange={onOfficeChange}>
                            <SelectTrigger className="w-full"><SelectValue placeholder={t('top_up_cards.office.name')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('top_up_cards.office.all_offices')}</SelectItem>
                                <SelectItem value="unassigned">{t('top_up_cards.office.not_assigned')}</SelectItem>
                                {offices.map((office) => <SelectItem key={office.id} value={String(office.id)}>{office.name}</SelectItem>)}
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
                    <FormControl compact className="w-full shrink-0 sm:w-35">
                        <Select value={pointFilter || 'all'} onValueChange={onPointChange}>
                            <SelectTrigger className="w-full"><SelectValue placeholder={t('top_up_cards.point')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('top_up_cards.office.all_points')}</SelectItem>
                                {points.map((point) => <SelectItem key={point} value={String(point)}>{point}</SelectItem>)}
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
                    <Button type="button" size="sm" onClick={onAssign}>
                        {t('top_up_cards.office.assign')}
                    </Button>
                </div>
            ) : null}
            columns={[
                { id: 'serial_no', header: t('top_up_cards.serial_no'), mobile: 'title', className: 'font-mono text-[12px]', cell: (row) => row.serial_no },
                { id: 'amount', header: t('top_up_cards.point'), mobile: 'meta', cell: (row) => formatTopUpAmount(row.amount) },
                { id: 'status', header: t('common.status'), mobile: 'badge', cell: (row) => <StatusBadge status={row.status} /> },
                { id: 'expires_at', header: t('top_up_cards.expires_at'), cell: (row) => formatDate(row.expires_at) ?? '—' },
                { id: 'batch_no', header: t('top_up_cards.batch_no'), cell: (row) => row.batch_no ?? '—' },
                { id: 'office', header: t('top_up_cards.office.name'), cell: (row) => row.office ?? t('top_up_cards.office.not_assigned') },
            ]}
        />
    );
}
