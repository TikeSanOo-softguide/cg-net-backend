import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { validateOffice, validateOfficeCdUnique, validateOfficeField, type OfficeFormValues } from '@/lib/office-validation';

type OfficeRow = { id: number; name: string; cd: number; address: string; top_up_cards_count: number };
type TopUpCardOfficeFormProps = {
    item: OfficeRow | null;
    officeCds: number[];
    onClose: () => void;
};

type TouchedFields = Record<keyof OfficeFormValues, boolean>;
const untouched: TouchedFields = { name: false, cd: false, address: false };

export function TopUpCardOfficeForm({ item, officeCds, onClose }: TopUpCardOfficeFormProps) {
    const { t } = useTranslation();
    const form = useForm<OfficeFormValues>({ name: item?.name ?? '', cd: item?.cd ?? '', address: item?.address ?? '' });
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof OfficeFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const fieldError = (field: keyof OfficeFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }
        if (field === 'cd') {
            return validateOfficeCdUnique(form.data.cd, officeCds, item?.cd, t) ?? form.errors[field] ?? validateOfficeField(field, form.data, t);
        }

        return form.errors[field] || validateOfficeField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({ name: true, cd: true, address: true });
        const errors = validateOffice(form.data, t);
        const cdError = validateOfficeCdUnique(form.data.cd, officeCds, item?.cd, t);
        if (cdError) {
            errors.cd = cdError;
        }
        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }
        const options = { preserveScroll: true, onSuccess: onClose };
        item ? form.put(`/top-up-cards/offices/${item.id}`, options) : form.post('/top-up-cards/offices', options);
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="grid gap-4 overflow-y-auto px-5 py-5">
                <FormField label={t('top_up_cards.office.name')} htmlFor="office-name" required error={fieldError('name')}>
                    <Input id="office-name" value={form.data.name} aria-invalid={Boolean(fieldError('name'))} onBlur={() => markTouched('name')} onChange={(event) => { markTouched('name'); form.setData('name', event.target.value); form.clearErrors('name'); }} />
                </FormField>
                <FormField label={t('top_up_cards.office.cd')} htmlFor="office-cd" required error={fieldError('cd')}>
                    <Input id="office-cd" type="number" min="10" max="99" step="1" value={form.data.cd} aria-invalid={Boolean(fieldError('cd'))} onBlur={() => markTouched('cd')} onChange={(event) => { const value = event.target.value; markTouched('cd'); form.setData('cd', value === '' ? '' : Number(value)); form.clearErrors('cd'); }} />
                </FormField>
                <FormField label={t('top_up_cards.office.address')} htmlFor="office-address" required error={fieldError('address')}>
                    <Input id="office-address" value={form.data.address} aria-invalid={Boolean(fieldError('address'))} onBlur={() => markTouched('address')} onChange={(event) => { form.setData('address', event.target.value); form.clearErrors('address'); }} />
                </FormField>
            </div>
            <FormActionBar onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'} />
        </form>
    );
}
