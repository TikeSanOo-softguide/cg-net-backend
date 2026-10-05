import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PlusIcon, UserRoundIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TopUpCardOfficeForm } from '@/components/top-up-cards/TopUpCardOfficeForm';
import { TopUpCardOfficeTable } from '@/components/top-up-cards/TopUpCardOfficeTable';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type OfficeRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };

type Props = {
    offices: Paginated<OfficeRow>;
    officeCds: number[];
    filters: { search: string };
};

export default function OfficePage({ offices, officeCds, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<OfficeRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<OfficeRow | null>(null);

    const refresh = (value: string) => {
        setSearch(value);
        window.setTimeout(
            () =>
                router.get(
                    '/top-up-cards/offices',
                    { search: value || undefined },
                    { preserveState: true, preserveScroll: true, replace: true },
                ),
            300,
        );
    };

    return (
        <>
            <Head title={t('top_up_cards.office.title')} />
            <PageContent>
                <PageHeader />
                <TopUpCardOfficeTable
                    offices={offices.data}
                    pagination={offices}
                    search={search}
                    onSearchChange={refresh}
                    onCreate={() => {
                        setEditing(null);
                        setFormOpen(true);
                    }}
                    onEdit={(row) => {
                        setEditing(row);
                        setFormOpen(true);
                    }}
                    onDelete={setDeleting}
                />
            </PageContent>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? t('top_up_cards.office.edit') : t('top_up_cards.office.add')}
                description={t('top_up_cards.office.description')}
                icon={editing ? UserRoundIcon : PlusIcon}
            >
                {formOpen ? (
                    <TopUpCardOfficeForm item={editing} officeCds={officeCds} onClose={() => setFormOpen(false)} />
                ) : null}
            </FormDialog>
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                title={t('top_up_cards.office.delete_title')}
                description={t('top_up_cards.office.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (deleting)
                        router.delete(`/top-up-cards/offices/${deleting.id}`, {
                            preserveScroll: true,
                            onFinish: () => setDeleting(null),
                        });
                }}
            />
        </>
    );
}
