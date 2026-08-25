import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, IdCardIcon, LanguagesIcon, MailIcon, MapPinIcon, PhoneIcon, UserIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormCard } from '@/components/FormCard';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type CustomerFormValues = {
    name: string;
    phone: string;
    nrc_number: string;
    email: string;
    address: string;
    language_pref: string;
    status: string;
};

export function emptyCustomerForm(): CustomerFormValues {
    return {
        name: '',
        phone: '',
        nrc_number: '',
        email: '',
        address: '',
        language_pref: 'my',
        status: 'active',
    };
}

type CustomerFormProps = {
    form: InertiaFormProps<CustomerFormValues>;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    cancelHref?: string;
    onCancel?: () => void;
    variant?: 'page' | 'modal';
};

export function CustomerForm({ form, onSubmit, submitLabel, cancelHref, onCancel, variant = 'page' }: CustomerFormProps) {
    const { t } = useTranslation();

    const fields = (
        <>
            <FormField label={t('customers.name')} htmlFor="name" error={form.errors.name} icon={UserIcon} className="sm:col-span-2">
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    required
                    aria-invalid={Boolean(form.errors.name)}
                />
            </FormField>
            <FormField label={t('customers.phone')} htmlFor="phone" error={form.errors.phone} icon={PhoneIcon}>
                <Input
                    id="phone"
                    value={form.data.phone}
                    onChange={(event) => form.setData('phone', event.target.value)}
                    placeholder={t('customers.phone_placeholder')}
                    required
                    aria-invalid={Boolean(form.errors.phone)}
                />
            </FormField>
            <FormField label={t('customers.nrc')} htmlFor="nrc_number" error={form.errors.nrc_number} icon={IdCardIcon}>
                <Input
                    id="nrc_number"
                    value={form.data.nrc_number}
                    onChange={(event) => form.setData('nrc_number', event.target.value)}
                    placeholder={t('customers.nrc_placeholder')}
                    required
                    aria-invalid={Boolean(form.errors.nrc_number)}
                />
            </FormField>
            <FormField label={t('customers.email')} htmlFor="email" error={form.errors.email} icon={MailIcon}>
                <Input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    aria-invalid={Boolean(form.errors.email)}
                />
            </FormField>
            <FormField label={t('customers.language')} htmlFor="language_pref" error={form.errors.language_pref} icon={LanguagesIcon}>
                <Select value={form.data.language_pref} onValueChange={(value) => form.setData('language_pref', value)}>
                    <SelectTrigger id="language_pref" className="w-full" aria-invalid={Boolean(form.errors.language_pref)}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="my">{t('language.my')}</SelectItem>
                        <SelectItem value="en">{t('language.en')}</SelectItem>
                        <SelectItem value="zh">{t('language.zh')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField label={t('common.status')} htmlFor="status" error={form.errors.status} icon={CircleDotIcon} className="sm:col-span-2">
                <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                    <SelectTrigger id="status" className="w-full" aria-invalid={Boolean(form.errors.status)}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">{t('status.active')}</SelectItem>
                        <SelectItem value="suspended">{t('status.suspended')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField label={t('customers.address')} htmlFor="address" error={form.errors.address} icon={MapPinIcon} className="sm:col-span-2">
                <Textarea
                    id="address"
                    value={form.data.address}
                    onChange={(event) => form.setData('address', event.target.value)}
                    aria-invalid={Boolean(form.errors.address)}
                />
            </FormField>
        </>
    );

    if (variant === 'modal') {
        return (
            <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
                <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                    <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">{fields}</div>
                </div>
                <FormActionBar variant="modal" onCancel={onCancel} processing={form.processing} submitLabel={submitLabel} />
            </form>
        );
    }

    return (
        <FormCard title={t('customers.profile')} icon={UserIcon}>
            <form onSubmit={onSubmit} className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {fields}
                <FormActionBar
                    cancelHref={cancelHref}
                    onCancel={onCancel}
                    processing={form.processing}
                    submitLabel={submitLabel}
                    className="sm:col-span-2"
                />
            </form>
        </FormCard>
    );
}
