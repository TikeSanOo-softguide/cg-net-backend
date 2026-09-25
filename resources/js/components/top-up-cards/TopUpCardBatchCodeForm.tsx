import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

type BatchCodeRow = { id: number; amount: number; batch_code: string };

type Props = { item: BatchCodeRow | null; onClose: () => void };

export function TopUpCardBatchCodeForm({ item, onClose }: Props) {
    const { t } = useTranslation();
    const form = useForm({ amount: item?.amount ?? '', batch_code: item?.batch_code ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        item
            ? form.put(`/top-up-cards/batch-codes/${item.id}`, { preserveScroll: true, onSuccess: onClose })
            : form.post('/top-up-cards/batch-codes', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="grid gap-4 overflow-y-auto px-5 py-5">
                <FormField
                    label={t('top_up_cards.batch_code.amount')}
                    htmlFor="batch-code-amount"
                    required
                    error={form.errors.amount}
                >
                    <Input
                        id="batch-code-amount"
                        type="number"
                        min="1"
                        max="1000000"
                        value={form.data.amount}
                        onChange={(event) =>
                            form.setData('amount', event.target.value === '' ? '' : Number(event.target.value))
                        }
                    />
                </FormField>
                <FormField
                    label={t('top_up_cards.batch_code.code')}
                    htmlFor="batch-code-code"
                    required
                    error={form.errors.batch_code}
                >
                    <Input
                        id="batch-code-code"
                        inputMode="numeric"
                        maxLength={4}
                        value={form.data.batch_code}
                        onChange={(event) =>
                            form.setData('batch_code', event.target.value.replace(/\D/g, '').slice(0, 4))
                        }
                    />
                </FormField>
            </div>
            <FormActionBar onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'} />
        </form>
    );
}
