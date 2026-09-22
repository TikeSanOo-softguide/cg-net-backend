import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { MapPinIcon, PencilIcon, PlusIcon, Trash2Icon, UserRoundIcon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { StatusBadge } from '@/components/StatusBadge';
import { FormField } from '@/components/ui/form-field';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { formatTopUpAmount, type TopUpCardRow } from '@/lib/top-up-cards';
import { formatDate } from '@/lib/utils';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { validateAgent, validateAgentField, type AgentFormValues } from '@/lib/agent-validation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };

type Props = {
    agents: AgentRow[];
    cards: TopUpCardRow[];
    batches: { id: number; batch_no: string }[];
    filters: { search: string; batch: string; agent: string };
};

function AgentForm({ item, onClose }: { item: AgentRow | null; onClose: () => void }) {
    const { t } = useTranslation();
    const form = useForm<AgentFormValues>({ name: item?.name ?? '', address: item?.address ?? '' });
    const validateField = (field: keyof AgentFormValues, value: string) => {
        const error = validateAgentField(field, { ...form.data, [field]: value }, t);

        error ? form.setError(field, error) : form.clearErrors(field);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const errors = validateAgent(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        const options = { preserveScroll: true, onSuccess: onClose };
        item ? form.put(`/top-up-cards/agents/${item.id}`, options) : form.post('/top-up-cards/agents', options);
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="grid gap-4 overflow-y-auto px-5 py-5">
                <FormField label={t('top_up_cards.agent.name')} htmlFor="agent-name" required error={form.errors.name}>
                    <Input id="agent-name" value={form.data.name} aria-invalid={Boolean(form.errors.name)} onChange={(event) => { form.setData('name', event.target.value); validateField('name', event.target.value); }} />
                </FormField>
                <FormField label={t('top_up_cards.agent.address')} htmlFor="agent-address" required error={form.errors.address}>
                    <Input id="agent-address" value={form.data.address} aria-invalid={Boolean(form.errors.address)} onChange={(event) => { form.setData('address', event.target.value); validateField('address', event.target.value); }} />
                </FormField>
            </div>
            <FormActionBar onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'} />
        </form>
    );
}

export default function AgentPage({ agents, cards, batches, filters }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<AgentRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<AgentRow | null>(null);
    const [selectedCardIds, setSelectedCardIds] = useState<string[]>([]);
    const [assigning, setAssigning] = useState(false);
    const [selectedAgent, setSelectedAgent] = useState('');
    const [batchFilter, setBatchFilter] = useState(filters.batch);
    const [agentFilter, setAgentFilter] = useState(filters.agent);

    const refresh = (value: string) => {
        setSearch(value);
        router.get('/top-up-cards/agents', { search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const refreshCards = (nextBatch: string, nextAgent: string) => {
        setBatchFilter(nextBatch);
        setAgentFilter(nextAgent);
        router.get('/top-up-cards/agents', {
            search: search || undefined,
            batch: nextBatch || undefined,
            agent: nextAgent || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title={t('top_up_cards.agent.title')} />
            <PageContent>
                <PageHeader />
                <DataTable
                    data={agents}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={refresh}
                    searchPlaceholder={t('top_up_cards.agent.search')}
                    emptyLabel={t('top_up_cards.agent.empty')}
                    onCreate={can('top-up-cards.create') ? () => { setEditing(null); setFormOpen(true); } : undefined}
                    createLabel={t('top_up_cards.agent.add')}
                    columns={[
                        { id: 'name', header: t('top_up_cards.agent.name'), mobile: 'title', className: 'font-medium', cell: (row) => row.name },
                        { id: 'address', header: t('top_up_cards.agent.address'), mobile: 'meta', cell: (row) => <span className="inline-flex items-center gap-1.5"><MapPinIcon className="size-3.5 text-muted-foreground" />{row.address}</span> },
                        { id: 'cards', header: t('top_up_cards.agent.assigned_cards'), cell: (row) => row.top_up_cards_count },
                    ]}
                    actions={(row) => <>
                        {can('top-up-cards.update') ? <TableActionButton label={t('common.edit')} icon={PencilIcon} tone="edit" onClick={() => { setEditing(row); setFormOpen(true); }} /> : null}
                        {can('top-up-cards.delete') ? <TableActionButton label={t('common.delete')} icon={Trash2Icon} tone="danger" onClick={() => setDeleting(row)} /> : null}
                    </>}
                />

                <div className="mt-6">
                    <DataTable
                        data={cards}
                        getRowId={(row) => String(row.id)}
                        search=""
                        onSearchChange={() => undefined}
                        searchPlaceholder={t('top_up_cards.search_placeholder')}
                        emptyLabel={t('top_up_cards.empty_table')}
                        filters={
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                                <FormControl compact className="w-full shrink-0 sm:w-48">
                                    <Select value={batchFilter || 'all'} onValueChange={(value) => refreshCards(value === 'all' ? '' : value, agentFilter)}>
                                        <SelectTrigger className="w-full"><SelectValue placeholder={t('top_up_cards.batch_no')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('top_up_cards.agent.all_batches')}</SelectItem>
                                            {batches.map((batch) => <SelectItem key={batch.id} value={String(batch.id)}>{batch.batch_no}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormControl compact className="w-full shrink-0 sm:w-48">
                                    <Select value={agentFilter || 'all'} onValueChange={(value) => refreshCards(batchFilter, value === 'all' ? '' : value)}>
                                        <SelectTrigger className="w-full"><SelectValue placeholder={t('top_up_cards.agent.name')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('top_up_cards.agent.all_agents')}</SelectItem>
                                            <SelectItem value="unassigned">{t('top_up_cards.agent.not_assigned')}</SelectItem>
                                            {agents.map((agent) => <SelectItem key={agent.id} value={String(agent.id)}>{agent.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                            </div>
                        }
                        selectable
                        selectedIds={selectedCardIds}
                        onSelectionChange={setSelectedCardIds}
                        isRowSelectable={(row) => row.status !== 'used'}
                        alwaysShowBulkActions
                        bulkActions={can('top-up-cards.update') ? (
                            <Button
                                type="button"
                                size="sm"
                                disabled={selectedCardIds.length === 0}
                                onClick={() => setAssigning(true)}
                            >
                                {t('top_up_cards.agent.assign')}
                            </Button>
                        ) : null}
                        columns={[
                            { id: 'serial_no', header: t('top_up_cards.serial_no'), mobile: 'title', className: 'font-mono text-[12px]', cell: (row) => row.serial_no },
                            { id: 'amount', header: t('top_up_cards.amount'), mobile: 'meta', cell: (row) => formatTopUpAmount(row.amount) },
                            { id: 'status', header: t('common.status'), mobile: 'badge', cell: (row) => <StatusBadge status={row.status} /> },
                            { id: 'expires_at', header: t('top_up_cards.expires_at'), cell: (row) => formatDate(row.expires_at) ?? '—' },
                            { id: 'batch_no', header: t('top_up_cards.batch_no'), cell: (row) => row.batch_no ?? '—' },
                            { id: 'agent', header: t('top_up_cards.agent.name'), cell: (row) => row.agent ?? t('top_up_cards.agent.not_assigned') },
                        ]}
                    />
                </div>
            </PageContent>

            <FormDialog open={formOpen} onOpenChange={setFormOpen} title={editing ? t('top_up_cards.agent.edit') : t('top_up_cards.agent.add')} description={t('top_up_cards.agent.description')} icon={editing ? UserRoundIcon : PlusIcon}>
                {formOpen ? <AgentForm item={editing} onClose={() => setFormOpen(false)} /> : null}
            </FormDialog>
            <FormDialog open={assigning} onOpenChange={setAssigning} title={t('top_up_cards.agent.assign_cards')} description={t('top_up_cards.agent.selected').replace(':count', String(selectedCardIds.length))} icon={UserRoundIcon}>
                {assigning ? (
                    <form className="flex min-h-0 flex-1 flex-col" onSubmit={(event) => { event.preventDefault(); router.patch('/top-up-cards/assign-agent', { card_ids: selectedCardIds.map(Number), agent_id: selectedAgent && selectedAgent !== 'none' ? Number(selectedAgent) : null }, { preserveScroll: true, onSuccess: () => { setSelectedCardIds([]); setSelectedAgent(''); setAssigning(false); } }); }}>
                        <div className="px-5 py-5">
                            <FormField label={t('top_up_cards.agent.name')} htmlFor="assign-agent">
                                <Select value={selectedAgent} onValueChange={setSelectedAgent}>
                                    <SelectTrigger id="assign-agent"><SelectValue placeholder={t('top_up_cards.agent.select')} /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('top_up_cards.agent.no_agent')}</SelectItem>
                                        {agents.map((agent) => <SelectItem key={agent.id} value={String(agent.id)}>{agent.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                        <FormActionBar onCancel={() => setAssigning(false)} submitLabel={t('top_up_cards.agent.assign')} />
                    </form>
                ) : null}
            </FormDialog>
            <ConfirmDialog open={deleting !== null} onOpenChange={(open) => { if (!open) setDeleting(null); }} title={t('top_up_cards.agent.delete_title')} description={t('top_up_cards.agent.delete_description')} destructive confirmLabel={t('common.delete')} onConfirm={() => { if (deleting) router.delete(`/top-up-cards/agents/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} />
        </>
    );
}
