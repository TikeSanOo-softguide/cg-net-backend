import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };
type BatchRow = { id: number; batch_no: string; expires_at: string | null; available_cards_count: number; available_points: number[] };

type TopUpCardAgentAssignmentFormProps = {
    agents: AgentRow[];
    batches: BatchRow[];
    batchId: string;
    onClose: () => void;
    onSuccess: () => void;
};

export function TopUpCardAgentAssignmentForm({
    agents,
    batches,
    batchId,
    onClose,
    onSuccess,
}: TopUpCardAgentAssignmentFormProps) {
    const { t } = useTranslation();
    const [selectedPoint, setSelectedPoint] = useState('');
    const [selectedAgent, setSelectedAgent] = useState('');
    const selectedBatch = batches.find((batch) => String(batch.id) === batchId);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.patch('/top-up-cards/assign-agent', {
            batch_id: Number(batchId),
            amount: Number(selectedPoint),
            agent_id: selectedAgent && selectedAgent !== 'none' ? Number(selectedAgent) : null,
        }, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
            <div className="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                <FormField label={t('top_up_cards.batch_no')} htmlFor="assign-batch">
                    <div id="assign-batch" className="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm">
                        {selectedBatch ? `#${selectedBatch.id} ${selectedBatch.batch_no}` : '—'}
                    </div>
                </FormField>
                <FormField label={t('top_up_cards.expires_at')} htmlFor="assign-expires-at">
                    <div id="assign-expires-at" className="flex h-9 items-center rounded-md border border-input bg-muted px-3 text-sm">
                        {formatDate(selectedBatch?.expires_at) ?? '—'}
                    </div>
                </FormField>
                <div>
                    <FormField label={t('top_up_cards.point')} htmlFor="assign-point">
                        <Select value={selectedPoint} onValueChange={setSelectedPoint} disabled={!selectedBatch}>
                            <SelectTrigger id="assign-point"><SelectValue placeholder={t('top_up_cards.point')} /></SelectTrigger>
                            <SelectContent>
                                {selectedBatch?.available_points.map((point) => (
                                    <SelectItem key={point} value={String(point)}>{point}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <div>
                    <FormField label={t('top_up_cards.agent.name')} htmlFor="assign-agent">
                        <Select value={selectedAgent} onValueChange={setSelectedAgent}>
                            <SelectTrigger id="assign-agent"><SelectValue placeholder={t('top_up_cards.agent.select')} /></SelectTrigger>
                            <SelectContent>
                                {agents.map((agent) => <SelectItem key={agent.id} value={String(agent.id)}>{agent.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
            </div>
            <FormActionBar onCancel={onClose}>
                <Button type="submit" size="sm" variant="primary" disabled={!selectedBatch || !selectedPoint || !selectedAgent}>
                    {t('top_up_cards.agent.assign')}
                </Button>
            </FormActionBar>
        </form>
    );
}
