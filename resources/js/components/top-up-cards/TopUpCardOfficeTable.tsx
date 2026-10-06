import { MapPinIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { TableActionButton } from '@/components/TableActionButton';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type OfficeRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };

type TopUpCardOfficeTableProps = {
    offices: OfficeRow[];
    pagination?: Paginated<OfficeRow>;
    search: string;
    onSearchChange: (value: string) => void;
    onCreate: () => void;
    onEdit: (office: OfficeRow) => void;
    onDelete: (office: OfficeRow) => void;
};

export function TopUpCardOfficeTable({
    offices,
    pagination,
    search,
    onSearchChange,
    onCreate,
    onEdit,
    onDelete,
}: TopUpCardOfficeTableProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <DataTable
            data={offices}
            pagination={pagination}
            getRowId={(row) => String(row.id)}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder={t('top_up_cards.office.search')}
            emptyLabel={t('top_up_cards.office.empty')}
            onCreate={can('top-up-cards.create') ? onCreate : undefined}
            createLabel={t('top_up_cards.office.add')}
            columns={[
                { id: 'name', header: t('top_up_cards.office.name'), mobile: 'title', className: 'font-medium', cell: (row) => row.name },
                { id: 'cd', header: t('top_up_cards.office.cd'), cell: (row) => row.cd ?? '-' },
                { id: 'address', header: t('top_up_cards.office.address'), mobile: 'meta', cell: (row) => <span className="inline-flex items-center gap-1.5"><MapPinIcon className="size-3.5 text-muted-foreground" />{row.address}</span> },
                { id: 'cards', header: t('top_up_cards.office.assigned_cards'), cell: (row) => row.top_up_cards_count },
            ]}
            actions={(row) => <>
                {can('top-up-cards.update') ? <TableActionButton label={t('common.edit')} icon={PencilIcon} tone="edit" onClick={() => onEdit(row)} /> : null}
                {can('top-up-cards.delete') ? <TableActionButton label={t('common.delete')} icon={Trash2Icon} tone="danger" onClick={() => onDelete(row)} /> : null}
            </>}
        />
    );
}
