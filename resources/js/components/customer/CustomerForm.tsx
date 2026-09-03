import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, LockIcon, UserIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { PhoneField } from '@/components/customer/PhoneField';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type CustomerFormValues = {
    name: string;
    phone: string;
    password: string;
    password_confirmation: string;
    status: string;
};

export function emptyCustomerForm(): CustomerFormValues {
    return {
        name: '',
        phone: '',
        password: '',
        password_confirmation: '',
        status: 'active',
    };
}

type CustomerFormProps = {
    form: InertiaFormProps<CustomerFormValues>;
    onSubmit: (event: FormEvent) => void;
    mode?: 'create' | 'edit';
    submitLabel?: string;
    onCancel?: () => void;
};

export function CustomerForm({ form, onSubmit, mode = 'create', submitLabel, onCancel }: CustomerFormProps) {
    const { t } = useTranslation();
    const passwordRequired = mode === 'create';

    return (
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField label={t('customers.name')} htmlFor="name" error={form.errors.name} icon={UserIcon} required className="sm:col-span-2">
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            required
                            aria-invalid={Boolean(form.errors.name)}
                        />
                    </FormField>
                    <FormField label={t('customers.phone')} htmlFor="phone" error={form.errors.phone} required>
                        <PhoneField
                            id="phone"
                            value={form.data.phone}
                            onChange={(phone) => form.setData('phone', phone)}
                            required
                            invalid={Boolean(form.errors.phone)}
                        />
                    </FormField>
                    <FormField label={t('common.status')} htmlFor="status" error={form.errors.status} icon={CircleDotIcon} required>
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
                    <FormField
                        label={t('customers.password')}
                        htmlFor="password"
                        error={form.errors.password}
                        icon={LockIcon}
                        required={passwordRequired}
                    >
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            required={passwordRequired}
                            autoComplete="new-password"
                            placeholder={passwordRequired ? undefined : t('customers.password_optional')}
                            aria-invalid={Boolean(form.errors.password)}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('customers.password_confirmation')}
                        htmlFor="password_confirmation"
                        error={form.errors.password_confirmation}
                        icon={LockIcon}
                        required={passwordRequired}
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={form.data.password_confirmation}
                            required={passwordRequired || form.data.password.length > 0}
                            autoComplete="new-password"
                            aria-invalid={Boolean(form.errors.password_confirmation)}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                    </FormField>
                </div>
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} submitLabel={submitLabel} />
        </form>
    );
}
