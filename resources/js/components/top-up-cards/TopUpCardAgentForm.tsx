import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { validateAgent, validateAgentCdUnique, validateAgentField, type AgentFormValues } from '@/lib/agent-validation';

type AgentRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };
type TopUpCardAgentFormProps = {
    item: AgentRow | null;
    agentCds: number[];
    onClose: () => void;
};

type TouchedFields = Record<keyof AgentFormValues, boolean>;
const untouched: TouchedFields = { name: false, cd: false, address: false };

export function TopUpCardAgentForm({ item, agentCds, onClose }: TopUpCardAgentFormProps) {
    const { t } = useTranslation();
    const form = useForm<AgentFormValues>({ name: item?.name ?? '', cd: item?.cd ?? '', address: item?.address ?? '' });
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof AgentFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const fieldError = (field: keyof AgentFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }
        if (field === 'cd') {
            return validateAgentCdUnique(form.data.cd, agentCds, item?.cd, t) ?? form.errors[field] ?? validateAgentField(field, form.data, t);
        }

        return form.errors[field] || validateAgentField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({ name: true, cd: true, address: true });
        const errors = validateAgent(form.data, t);
        const cdError = validateAgentCdUnique(form.data.cd, agentCds, item?.cd, t);
        if (cdError) {
            errors.cd = cdError;
        }
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
                <FormField label={t('top_up_cards.agent.name')} htmlFor="agent-name" required error={fieldError('name')}>
                    <Input id="agent-name" value={form.data.name} aria-invalid={Boolean(fieldError('name'))} onBlur={() => markTouched('name')} onChange={(event) => { markTouched('name'); form.setData('name', event.target.value); form.clearErrors('name'); }} />
                </FormField>
                <FormField label={t('top_up_cards.agent.cd')} htmlFor="agent-cd" required error={fieldError('cd')}>
                    <Input id="agent-cd" type="number" min="10" max="99" step="1" value={form.data.cd} aria-invalid={Boolean(fieldError('cd'))} onBlur={() => markTouched('cd')} onChange={(event) => { const value = event.target.value; markTouched('cd'); form.setData('cd', value === '' ? '' : Number(value)); form.clearErrors('cd'); }} />
                </FormField>
                <FormField label={t('top_up_cards.agent.address')} htmlFor="agent-address" required error={fieldError('address')}>
                    <Input id="agent-address" value={form.data.address} aria-invalid={Boolean(fieldError('address'))} onBlur={() => markTouched('address')} onChange={(event) => { form.setData('address', event.target.value); form.clearErrors('address'); }} />
                </FormField>
            </div>
            <FormActionBar onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'} />
        </form>
    );
}
