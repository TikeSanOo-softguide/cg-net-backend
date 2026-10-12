import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

import type { SupportContactItem } from '@/components/settings/general-settings/SupportContact/SupportContactFormDialog';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    items: SupportContactItem[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    onCreate: () => void;
    onEdit: (item: SupportContactItem) => void;
    onDelete: (item: SupportContactItem) => void;
};

export function SupportContactTable({ items, canCreate, canUpdate, canDelete, onCreate, onEdit, onDelete }: Props) {
    const { t } = useTranslation();

    return (
        <section className="rounded-xl border bg-card">
            <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                <h2 className="text-sm font-semibold">{t('settings.general_settings.support_contact')}</h2>

                {canCreate ? (
                    <Button type="button" size="sm" variant="outline" onClick={onCreate}>
                        <PlusIcon className="size-4" />
                        {t('common.create')}
                    </Button>
                ) : null}
            </div>

            {items.length ? (
                <ul className="divide-y">
                    {items.map((contact) => (
                        <li key={contact.id} className="flex items-center justify-between gap-4 px-5 py-4">
                            <span className="text-sm">{contact.phone}</span>

                            {canUpdate || canDelete ? (
                                <div className="flex shrink-0 items-center gap-1">
                                    {canUpdate ? (
                                        <TableActionButton
                                            label={t('common.edit')}
                                            icon={PencilIcon}
                                            tone="edit"
                                            onClick={() => onEdit(contact)}
                                        />
                                    ) : null}

                                    {canDelete ? (
                                        <TableActionButton
                                            label={t('common.delete')}
                                            icon={Trash2Icon}
                                            tone="danger"
                                            onClick={() => onDelete(contact)}
                                        />
                                    ) : null}
                                </div>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="px-5 py-4 text-sm text-muted-foreground">—</p>
            )}
        </section>
    );
}
