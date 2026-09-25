import { PencilIcon, Trash2Icon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import { TableActionButton } from '@/components/TableActionButton';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type BatchCodeRow = { id: number; amount: number; batch_code: string };

type Props = {
    items: BatchCodeRow[];
    onCreate: () => void;
    onEdit: (item: BatchCodeRow) => void;
    onDelete: (item: BatchCodeRow) => void;
};

export function TopUpCardBatchCodeTable({ items, onCreate, onEdit, onDelete }: Props) {
    const { t } = useTranslation();
    const can = useCan();

    return (
        <DataTable
            title={t('top_up_cards.batch_code.title')}
            data={items}
            getRowId={(row) => String(row.id)}
            emptyLabel={t('top_up_cards.batch_code.empty')}
            onCreate={can('top-up-cards.create') ? onCreate : undefined}
            createLabel={t('top_up_cards.batch_code.add')}
            columns={[
                {
                    id: 'amount',
                    header: t('top_up_cards.batch_code.amount'),
                    mobile: 'title',
                    cell: (row) => row.amount,
                },
                {
                    id: 'batch_code',
                    header: t('top_up_cards.batch_code.code'),
                    mobile: 'meta',
                    className: 'font-mono',
                    cell: (row) => row.batch_code,
                },
            ]}
            actions={(row) => (
                <>
                    {can('top-up-cards.update') ? (
                        <TableActionButton
                            label={t('common.edit')}
                            icon={PencilIcon}
                            tone="edit"
                            onClick={() => onEdit(row)}
                        />
                    ) : null}
                    {can('top-up-cards.delete') ? (
                        <TableActionButton
                            label={t('common.delete')}
                            icon={Trash2Icon}
                            tone="danger"
                            onClick={() => onDelete(row)}
                        />
                    ) : null}
                </>
            )}
        />
    );
}
