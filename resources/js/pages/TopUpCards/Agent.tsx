import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PlusIcon, UserRoundIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TopUpCardAgentAssignmentForm } from '@/components/top-up-cards/TopUpCardAgentAssignmentForm';
import { TopUpCardAgentForm } from '@/components/top-up-cards/TopUpCardAgentForm';
import { TopUpCardAgentCardTable } from '@/components/top-up-cards/TopUpCardAgentCardTable';
import { TopUpCardAgentTable } from '@/components/top-up-cards/TopUpCardAgentTable';
import type { TopUpCardRow } from '@/lib/top-up-cards';
import { useTranslation } from '@/hooks/useTranslation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };

type Props = {
    agents: AgentRow[];
    cards: TopUpCardRow[];
    batches: { id: number; batch_no: string }[];
    filters: { search: string; card_search: string; batch: string; agent: string; status: string };
};

function visitAgents(filters: Props['filters']) {
    router.get('/top-up-cards/agents', {
        search: filters.search || undefined,
        card_search: filters.card_search || undefined,
        batch: filters.batch,
        agent: filters.agent || undefined,
        status: filters.status,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function AgentPage({ agents, cards, batches, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<AgentRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<AgentRow | null>(null);
    const [selectedCardIds, setSelectedCardIds] = useState<string[]>([]);
    const [assigning, setAssigning] = useState(false);
    const [batchFilter, setBatchFilter] = useState(filters.batch);
    const [agentFilter, setAgentFilter] = useState(filters.agent);
    const [statusFilter, setStatusFilter] = useState(filters.status);

    const [cardSearch, setCardSearch] = useState(filters.card_search);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
        setCardSearch(filters.card_search);
        setBatchFilter(filters.batch);
        setAgentFilter(filters.agent);
        setStatusFilter(filters.status);
    }, [filters.search, filters.card_search, filters.batch, filters.agent, filters.status]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const refresh = (value: string) => {
        setSearch(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(() => {
            visitAgents({ search: value, card_search: cardSearch, batch: batchFilter, agent: agentFilter, status: statusFilter });
        }, 300);
    };

    const refreshCards = (nextBatch: string, nextAgent: string, nextStatus: string, nextSearch = cardSearch, debounceSearch = false) => {
        setCardSearch(nextSearch);
        setBatchFilter(nextBatch);
        setAgentFilter(nextAgent);
        setStatusFilter(nextStatus);
        window.clearTimeout(debounce.current);
        if (debounceSearch) {
            debounce.current = window.setTimeout(() => {
                visitAgents({ search, card_search: nextSearch, batch: nextBatch, agent: nextAgent, status: nextStatus });
            }, 300);

            return;
        }

        visitAgents({ search, card_search: nextSearch, batch: nextBatch, agent: nextAgent, status: nextStatus });
    };

    return (
        <>
            <Head title={t('top_up_cards.agent.title')} />
            <PageContent>
                <PageHeader />
                <TopUpCardAgentTable
                    agents={agents}
                    search={search}
                    onSearchChange={refresh}
                    onCreate={() => { setEditing(null); setFormOpen(true); }}
                    onEdit={(row) => { setEditing(row); setFormOpen(true); }}
                    onDelete={setDeleting}
                />

                <div className="mt-6">
                    <TopUpCardAgentCardTable
                        cards={cards}
                        agents={agents}
                        batches={batches}
                        search={cardSearch}
                        batchFilter={batchFilter}
                        agentFilter={agentFilter}
                        statusFilter={statusFilter}
                        selectedIds={selectedCardIds}
                        onSearchChange={(value) => refreshCards(batchFilter, agentFilter, statusFilter, value, true)}
                        onBatchChange={(value) => refreshCards(value === 'all' ? '' : value, agentFilter, statusFilter)}
                        onAgentChange={(value) => refreshCards(batchFilter, value === 'all' ? '' : value, statusFilter)}
                        onStatusChange={(value) => refreshCards(batchFilter, agentFilter, value === 'all' ? '' : value)}
                        onSelectionChange={setSelectedCardIds}
                        onAssign={() => setAssigning(true)}
                    />
                </div>
            </PageContent>

            <FormDialog open={formOpen} onOpenChange={setFormOpen} title={editing ? t('top_up_cards.agent.edit') : t('top_up_cards.agent.add')} description={t('top_up_cards.agent.description')} icon={editing ? UserRoundIcon : PlusIcon}>
                {formOpen ? <TopUpCardAgentForm item={editing} onClose={() => setFormOpen(false)} /> : null}
            </FormDialog>
            <FormDialog open={assigning} onOpenChange={setAssigning} title={t('top_up_cards.agent.assign_cards')} description={t('top_up_cards.agent.selected').replace(':count', String(selectedCardIds.length))} icon={UserRoundIcon}>
                {assigning ? <TopUpCardAgentAssignmentForm agents={agents} selectedCardIds={selectedCardIds} onClose={() => setAssigning(false)} onSuccess={() => { setSelectedCardIds([]); setAssigning(false); }} /> : null}
            </FormDialog>
            <ConfirmDialog open={deleting !== null} onOpenChange={(open) => { if (!open) setDeleting(null); }} title={t('top_up_cards.agent.delete_title')} description={t('top_up_cards.agent.delete_description')} destructive confirmLabel={t('common.delete')} onConfirm={() => { if (deleting) router.delete(`/top-up-cards/agents/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} />
        </>
    );
}
