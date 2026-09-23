import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { validateAgent, validateAgentField, type AgentFormValues } from '@/lib/agent-validation';

type AgentRow = { id: number; name: string; address: string; top_up_cards_count: number };

type TopUpCardAgentFormProps = {
    item: AgentRow | null;
    onClose: () => void;
};

export function TopUpCardAgentForm({ item, onClose }: TopUpCardAgentFormProps) {
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
