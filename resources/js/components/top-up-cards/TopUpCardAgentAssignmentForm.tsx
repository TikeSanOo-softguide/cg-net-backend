import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };

type TopUpCardAgentAssignmentFormProps = {
    agents: AgentRow[];
    selectedCardIds: string[];
    onClose: () => void;
    onSuccess: () => void;
};

export function TopUpCardAgentAssignmentForm({
    agents,
    selectedCardIds,
    onClose,
    onSuccess,
}: TopUpCardAgentAssignmentFormProps) {
    const { t } = useTranslation();
    const [selectedAgent, setSelectedAgent] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.patch('/top-up-cards/assign-agent', {
            card_ids: selectedCardIds.map(Number),
            agent_id: selectedAgent && selectedAgent !== 'none' ? Number(selectedAgent) : null,
        }, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
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
            <FormActionBar onCancel={onClose} submitLabel={t('top_up_cards.agent.assign')} />
        </form>
    );
}
