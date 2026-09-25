import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PlusIcon, UserRoundIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TopUpCardAgentForm } from '@/components/top-up-cards/TopUpCardAgentForm';
import { TopUpCardAgentTable } from '@/components/top-up-cards/TopUpCardAgentTable';
import { TopUpCardBatchCodeForm } from '@/components/top-up-cards/TopUpCardBatchCodeForm';
import { TopUpCardBatchCodeTable } from '@/components/top-up-cards/TopUpCardBatchCodeTable';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type AgentRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };
type BatchCodeRow = { id: number; amount: number; batch_code: string };

type Props = {
    agents: Paginated<AgentRow>;
    agentCds: number[];
    agentNames: string[];
    batchCodes: BatchCodeRow[];
    filters: { search: string };
};

export default function AgentPage({ agents, agentCds, agentNames, batchCodes, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<AgentRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<AgentRow | null>(null);
    const [editingBatchCode, setEditingBatchCode] = useState<BatchCodeRow | null>(null);
    const [batchCodeFormOpen, setBatchCodeFormOpen] = useState(false);
    const [deletingBatchCode, setDeletingBatchCode] = useState<BatchCodeRow | null>(null);

    const refresh = (value: string) => {
        setSearch(value);
        window.setTimeout(
            () =>
                router.get(
                    '/top-up-cards/agents',
                    { search: value || undefined },
                    { preserveState: true, preserveScroll: true, replace: true },
                ),
            300,
        );
    };

    return (
        <>
            <Head title={t('top_up_cards.agent.title')} />
            <PageContent>
                <PageHeader />
                <TopUpCardAgentTable
                    agents={agents.data}
                    pagination={agents}
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
                <div className="mt-6">
                    <TopUpCardBatchCodeTable
                        items={batchCodes}
                        onCreate={() => {
                            setEditingBatchCode(null);
                            setBatchCodeFormOpen(true);
                        }}
                        onEdit={(item) => {
                            setEditingBatchCode(item);
                            setBatchCodeFormOpen(true);
                        }}
                        onDelete={setDeletingBatchCode}
                    />
                </div>
            </PageContent>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? t('top_up_cards.agent.edit') : t('top_up_cards.agent.add')}
                description={t('top_up_cards.agent.description')}
                icon={editing ? UserRoundIcon : PlusIcon}
            >
                {formOpen ? (
                    <TopUpCardAgentForm
                        item={editing}
                        agentCds={agentCds}
                        agentNames={agentNames}
                        onClose={() => setFormOpen(false)}
                    />
                ) : null}
            </FormDialog>
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                title={t('top_up_cards.agent.delete_title')}
                description={t('top_up_cards.agent.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (deleting)
                        router.delete(`/top-up-cards/agents/${deleting.id}`, {
                            preserveScroll: true,
                            onFinish: () => setDeleting(null),
                        });
                }}
            />
            <FormDialog
                open={batchCodeFormOpen}
                onOpenChange={setBatchCodeFormOpen}
                title={editingBatchCode ? t('top_up_cards.batch_code.edit') : t('top_up_cards.batch_code.add')}
                description={t('top_up_cards.batch_code.description')}
                icon={editingBatchCode ? UserRoundIcon : PlusIcon}
            >
                {batchCodeFormOpen ? (
                    <TopUpCardBatchCodeForm item={editingBatchCode} onClose={() => setBatchCodeFormOpen(false)} />
                ) : null}
            </FormDialog>
            <ConfirmDialog
                open={deletingBatchCode !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingBatchCode(null);
                }}
                title={t('top_up_cards.batch_code.delete_title')}
                description={t('top_up_cards.batch_code.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (deletingBatchCode)
                        router.delete(`/top-up-cards/batch-codes/${deletingBatchCode.id}`, {
                            preserveScroll: true,
                            onFinish: () => setDeletingBatchCode(null),
                        });
                }}
            />
        </>
    );
}
