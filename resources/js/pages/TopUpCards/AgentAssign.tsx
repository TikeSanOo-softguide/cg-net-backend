import { useEffect, useRef, useState, type ChangeEvent } from 'react';
import { Head, router } from '@inertiajs/react';
import { UserRoundIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TopUpCardAgentAssignmentForm } from '@/components/top-up-cards/TopUpCardAgentAssignmentForm';
import { TopUpCardAgentCardTable } from '@/components/top-up-cards/TopUpCardAgentCardTable';
import type { TopUpCardRow } from '@/lib/top-up-cards';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };
type BatchRow = { id: number; batch_no: string; expires_at: string | null; available_cards_count: number; available_points: number[] };

type Props = {
    agents: AgentRow[];
    cards: Paginated<TopUpCardRow>;
    batches: BatchRow[];
    points: number[];
    filters: { search: string; card_search: string; batch: string; agent: string; status: string; amount: string };
};

function visitAssign(filters: Props['filters']) {
    router.get('/top-up-cards/agent-assign', {
        search: filters.search || undefined,
        card_search: filters.card_search || undefined,
        batch: filters.batch,
        agent: filters.agent || undefined,
        status: filters.status,
        amount: filters.amount,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function AgentAssignPage({ agents, cards, batches, points, filters }: Props) {
    const { t } = useTranslation();
    const [assigning, setAssigning] = useState(false);
    const [batchFilter, setBatchFilter] = useState(filters.batch);
    const [agentFilter, setAgentFilter] = useState(filters.agent);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const [pointFilter, setPointFilter] = useState(filters.amount);
    const [cardSearch, setCardSearch] = useState(filters.card_search);
    const debounce = useRef<number>(0);
    const importInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setCardSearch(filters.card_search);
        setBatchFilter(filters.batch);
        setAgentFilter(filters.agent);
        setStatusFilter(filters.status);
        setPointFilter(filters.amount);
    }, [filters.card_search, filters.batch, filters.agent, filters.status, filters.amount]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const refreshCards = (nextBatch: string, nextAgent: string, nextStatus: string, nextPoint: string, nextSearch = cardSearch, debounceSearch = false) => {
        setCardSearch(nextSearch);
        setBatchFilter(nextBatch);
        setAgentFilter(nextAgent);
        setStatusFilter(nextStatus);
        setPointFilter(nextPoint);
        window.clearTimeout(debounce.current);
        if (debounceSearch) {
            debounce.current = window.setTimeout(() => visitAssign({ ...filters, card_search: nextSearch, batch: nextBatch, agent: nextAgent, status: nextStatus, amount: nextPoint }), 300);
            return;
        }
        visitAssign({ ...filters, card_search: nextSearch, batch: nextBatch, agent: nextAgent, status: nextStatus, amount: nextPoint });
    };

    const importCards = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        router.post('/top-up-cards/agents/import', { file, return: 'assign' }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (importInput.current) importInput.current.value = '';
            },
        });
    };

    return (
        <>
            <Head title={t('top_up_cards.agent.assign_cards')} />
            <PageContent>
                <PageHeader />
                <input ref={importInput} type="file" accept=".csv,text/csv" className="hidden" onChange={importCards} />
                <TopUpCardAgentCardTable
                    cards={cards.data}
                    pagination={cards}
                    agents={agents}
                    batches={batches}
                    points={points}
                    search={cardSearch}
                    batchFilter={batchFilter}
                    agentFilter={agentFilter}
                    statusFilter={statusFilter}
                    pointFilter={pointFilter}
                    onSearchChange={(value) => refreshCards(batchFilter, agentFilter, statusFilter, pointFilter, value, true)}
                    onBatchChange={(value) => refreshCards(value === 'all' ? '' : value, agentFilter, statusFilter, pointFilter)}
                    onAgentChange={(value) => refreshCards(batchFilter, value === 'all' ? '' : value, statusFilter, pointFilter)}
                    onStatusChange={(value) => refreshCards(batchFilter, agentFilter, value === 'all' ? '' : value, pointFilter)}
                    onPointChange={(value) => refreshCards(batchFilter, agentFilter, statusFilter, value === 'all' ? '' : value)}
                    onAssign={() => setAssigning(true)}
                    onImport={() => importInput.current?.click()}
                />
            </PageContent>
            <FormDialog open={assigning} onOpenChange={setAssigning} title={t('top_up_cards.agent.assign_cards')} description={t('top_up_cards.agent.description')} icon={UserRoundIcon}>
                {assigning ? <TopUpCardAgentAssignmentForm agents={agents} batches={batches} batchId={batchFilter} onClose={() => setAssigning(false)} onSuccess={() => setAssigning(false)} /> : null}
            </FormDialog>
        </>
    );
}
