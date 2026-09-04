import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, LockIcon, UserIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { PhoneField } from '@/components/customer/PhoneField';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { validateCustomerField, validateCustomerForm } from '@/lib/customer-validation';

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

type TouchedFields = Record<keyof CustomerFormValues, boolean>;

const untouched: TouchedFields = {
    name: false,
    phone: false,
    password: false,
    password_confirmation: false,
    status: false,
};

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
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof CustomerFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof CustomerFormValues>(field: K, value: CustomerFormValues[K]) => {
        form.setData(field, value);
        form.clearErrors(field);

        if (field === 'password' && touched.password_confirmation) {
            form.clearErrors('password_confirmation');
        }
    };

    const fieldState = (field: keyof CustomerFormValues): 'idle' | 'error' => {
        if (! touched[field] && ! submitted) {
            return 'idle';
        }

        if (form.errors[field] || validateCustomerField(field, form.data, t, mode)) {
            return 'error';
        }

        return 'idle';
    };

    const fieldError = (field: keyof CustomerFormValues): string | undefined => {
        if (! touched[field] && ! submitted) {
            return undefined;
        }

        return form.errors[field] || validateCustomerField(field, form.data, t, mode);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            name: true,
            phone: true,
            password: true,
            password_confirmation: true,
            status: true,
        });

        const errors = validateCustomerForm(form.data, t, mode);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col" noValidate autoComplete="off">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField
                        label={t('customers.name')}
                        htmlFor="name"
                        error={fieldError('name')}
                        icon={UserIcon}
                        required
                        className="sm:col-span-2"
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            required
                            aria-invalid={fieldState('name') === 'error'}
                            className={formControlStateClass(fieldState('name'))}
                            onBlur={() => markTouched('name')}
                            onChange={(event) => setField('name', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('customers.phone')}
                        htmlFor="phone"
                        error={fieldError('phone')}
                        required
                    >
                        <PhoneField
                            id="phone"
                            value={form.data.phone}
                            required
                            invalid={fieldState('phone') === 'error'}
                            inputClassName={formControlStateClass(fieldState('phone'))}
                            onBlur={() => markTouched('phone')}
                            onChange={(phone) => setField('phone', phone)}
                        />
                    </FormField>
                    <FormField
                        label={t('common.status')}
                        htmlFor="status"
                        error={fieldError('status')}
                        icon={CircleDotIcon}
                        required
                    >
                        <Select
                            value={form.data.status}
                            onValueChange={(value) => {
                                setField('status', value);
                                markTouched('status');
                            }}
                        >
                            <SelectTrigger
                                id="status"
                                className={formControlStateClass(fieldState('status'))}
                                aria-invalid={fieldState('status') === 'error'}
                            >
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
                        error={fieldError('password')}
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
                            aria-invalid={fieldState('password') === 'error'}
                            className={formControlStateClass(fieldState('password'))}
                            onBlur={() => markTouched('password')}
                            onChange={(event) => setField('password', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('customers.password_confirmation')}
                        htmlFor="password_confirmation"
                        error={fieldError('password_confirmation')}
                        icon={LockIcon}
                        required={passwordRequired}
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={form.data.password_confirmation}
                            required={passwordRequired || form.data.password.length > 0}
                            autoComplete="new-password"
                            aria-invalid={fieldState('password_confirmation') === 'error'}
                            className={formControlStateClass(fieldState('password_confirmation'))}
                            onBlur={() => markTouched('password_confirmation')}
                            onChange={(event) => setField('password_confirmation', event.target.value)}
                        />
                    </FormField>
                </div>
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} submitLabel={submitLabel} />
        </form>
    );
}
