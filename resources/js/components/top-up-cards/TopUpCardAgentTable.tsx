import { MapPinIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { TableActionButton } from '@/components/TableActionButton';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };

type TopUpCardAgentTableProps = {
    agents: AgentRow[];
    search: string;
    onSearchChange: (value: string) => void;
    onCreate: () => void;
    onEdit: (agent: AgentRow) => void;
    onDelete: (agent: AgentRow) => void;
};

export function TopUpCardAgentTable({
    agents,
    search,
    onSearchChange,
    onCreate,
    onEdit,
    onDelete,
}: TopUpCardAgentTableProps) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <DataTable
            data={agents}
            getRowId={(row) => String(row.id)}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder={t('top_up_cards.agent.search')}
            emptyLabel={t('top_up_cards.agent.empty')}
            onCreate={can('top-up-cards.create') ? onCreate : undefined}
            createLabel={t('top_up_cards.agent.add')}
            columns={[
                { id: 'name', header: t('top_up_cards.agent.name'), mobile: 'title', className: 'font-medium', cell: (row) => row.name },
                { id: 'address', header: t('top_up_cards.agent.address'), mobile: 'meta', cell: (row) => <span className="inline-flex items-center gap-1.5"><MapPinIcon className="size-3.5 text-muted-foreground" />{row.address}</span> },
                { id: 'cards', header: t('top_up_cards.agent.assigned_cards'), cell: (row) => row.top_up_cards_count },
            ]}
            actions={(row) => <>
                {can('top-up-cards.update') ? <TableActionButton label={t('common.edit')} icon={PencilIcon} tone="edit" onClick={() => onEdit(row)} /> : null}
                {can('top-up-cards.delete') ? <TableActionButton label={t('common.delete')} icon={Trash2Icon} tone="danger" onClick={() => onDelete(row)} /> : null}
            </>}
        />
    );
}
