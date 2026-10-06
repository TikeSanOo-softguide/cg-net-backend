import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

import type { FaqItem } from '@/components/settings/general-settings/faq/FaqFormDialog';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import type { SupportedLocale } from '@/types';
import { truncateText } from '@/lib/utils';

type Props = {
    items: FaqItem[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    onCreate: () => void;
    onEdit: (item: FaqItem) => void;
    onDelete: (item: FaqItem) => void;
};

function localizedValue(item: FaqItem, field: 'title' | 'description', locale: SupportedLocale): string {
    return item[`${field}_${locale}`] || item[`${field}_en`];
}

export function FaqTable({ items, canCreate, canUpdate, canDelete, onCreate, onEdit, onDelete }: Props) {
    const { t, locale } = useTranslation();

    return (
        <section className="rounded-xl border bg-card shadow-sm">
            <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                <h2 className="text-sm font-semibold">{t('settings.general_settings.faq')}</h2>

                {canCreate ? (
                    <Button type="button" size="sm" variant="outline" onClick={onCreate}>
                        <PlusIcon className="size-4" />
                        {t('common.create')}
                    </Button>
                ) : null}
            </div>

            {items.length ? (
                <div className="divide-y">
                    {items.map((item) => (
                        <article key={item.id} className="flex items-start justify-between gap-4 px-5 py-4">
                            <div className="min-w-0 space-y-1">
                                <h3 className="text-sm font-medium">
                                    {truncateText(localizedValue(item, 'title', locale), 20)}
                                </h3>

                                <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                                    {truncateText(localizedValue(item, 'description', locale), 100)}
                                </p>
                            </div>

                            {canUpdate || canDelete ? (
                                <div className="flex shrink-0 items-center gap-1">
                                    {canUpdate ? (
                                        <TableActionButton
                                            label={t('common.edit')}
                                            icon={PencilIcon}
                                            tone="edit"
                                            onClick={() => onEdit(item)}
                                        />
                                    ) : null}

                                    {canDelete ? (
                                        <TableActionButton
                                            label={t('common.delete')}
                                            icon={Trash2Icon}
                                            tone="danger"
                                            onClick={() => onDelete(item)}
                                        />
                                    ) : null}
                                </div>
                            ) : null}
                        </article>
                    ))}
                </div>
            ) : (
                <p className="px-5 py-4 text-sm text-muted-foreground">—</p>
            )}
        </section>
    );
}
