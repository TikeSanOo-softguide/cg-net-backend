import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, LockIcon, AtSignIcon, ShieldIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { MultiSelect } from '@/components/MultiSelect';
import type { StaffFormValues, StaffRoleOption } from '@/components/staff/StaffForm';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { staffCreateSuccessMessage, validateStaffCreate, validateStaffCreateField } from '@/lib/staff-create-validation';
import { staffRoleCard } from '@/lib/staff-roles';
import { cn } from '@/lib/utils';

const autocompleteOff = {
    autoComplete: 'off',
    autoCorrect: 'off',
    autoCapitalize: 'off',
    spellCheck: false,
    'data-lpignore': 'true',
    'data-1p-ignore': 'true',
    'data-form-type': 'other',
} as const;

type TouchedFields = Record<keyof StaffFormValues, boolean>;

const untouched: TouchedFields = {
    username: false,
    password: false,
    password_confirmation: false,
    status: false,
    role_ids: false,
};

type StaffCreateFormProps = {
    form: InertiaFormProps<StaffFormValues>;
    roles: StaffRoleOption[];
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
};

export function StaffCreateForm({
    form,
    roles,
    onSubmit,
    onCancel,
    mode = 'create',
}: StaffCreateFormProps) {
    const { t } = useTranslation();
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof StaffFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof StaffFormValues>(field: K, value: StaffFormValues[K]) => {
        form.setData(field, value);
        form.clearErrors(field);

        if (field === 'password' && touched.password_confirmation) {
            form.clearErrors('password_confirmation');
        }
    };

    const fieldState = (field: keyof StaffFormValues): 'idle' | 'error' | 'success' => {
        if (! touched[field] && ! submitted) {
            return 'idle';
        }

        const serverError = form.errors[field];
        const clientError = validateStaffCreateField(field, form.data, t);

        if (serverError || clientError) {
            return 'error';
        }

        return 'success';
    };

    const fieldError = (field: keyof StaffFormValues): string | undefined => {
        if (! touched[field] && ! submitted) {
            return undefined;
        }

        return form.errors[field] || validateStaffCreateField(field, form.data, t);
    };

    const fieldSuccess = (field: keyof StaffFormValues): string | undefined => {
        return fieldState(field) === 'success' ? staffCreateSuccessMessage(field, t) : undefined;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            username: true,
            password: true,
            password_confirmation: true,
            status: true,
            role_ids: true,
        });

        const errors = validateStaffCreate(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    const fields = (
        <>
            <FormField
                label={t('staff.username')}
                htmlFor="staff-create-username"
                error={fieldError('username')}
                success={fieldSuccess('username')}
                icon={AtSignIcon}
                required
            >
                <Input
                    id="staff-create-username"
                    name="staff_create_username"
                    type="text"
                    value={form.data.username}
                    required
                    aria-invalid={fieldState('username') === 'error'}
                    className={formControlStateClass(fieldState('username'))}
                    onBlur={() => markTouched('username')}
                    onChange={(event) => setField('username', event.target.value)}
                    {...autocompleteOff}
                />
            </FormField>
            <FormField
                label={t('common.status')}
                htmlFor="staff-create-status"
                error={fieldError('status')}
                success={fieldSuccess('status')}
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
                        id="staff-create-status"
                        className={cn('w-full', formControlStateClass(fieldState('status')))}
                        aria-invalid={fieldState('status') === 'error'}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">{t('status.active')}</SelectItem>
                        <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField
                label={t('staff.password')}
                htmlFor="staff-create-password"
                error={fieldError('password')}
                success={fieldSuccess('password')}
                icon={LockIcon}
                required
            >
                <Input
                    id="staff-create-password"
                    name="staff_create_password"
                    type="password"
                    value={form.data.password}
                    required
                    aria-invalid={fieldState('password') === 'error'}
                    className={formControlStateClass(fieldState('password'))}
                    onBlur={() => markTouched('password')}
                    onChange={(event) => setField('password', event.target.value)}
                    {...autocompleteOff}
                />
            </FormField>
            <FormField
                label={t('staff.password_confirmation')}
                htmlFor="staff-create-password-confirmation"
                error={fieldError('password_confirmation')}
                success={fieldSuccess('password_confirmation')}
                icon={LockIcon}
                required
            >
                <Input
                    id="staff-create-password-confirmation"
                    name="staff_create_password_confirmation"
                    type="password"
                    value={form.data.password_confirmation}
                    required
                    aria-invalid={fieldState('password_confirmation') === 'error'}
                    className={formControlStateClass(fieldState('password_confirmation'))}
                    onBlur={() => markTouched('password_confirmation')}
                    onChange={(event) => setField('password_confirmation', event.target.value)}
                    {...autocompleteOff}
                />
            </FormField>
            <FormField
                label={t('staff.roles')}
                htmlFor="staff-create-role-ids"
                error={fieldError('role_ids')}
                success={fieldSuccess('role_ids')}
                required
                className="sm:col-span-2"
            >
                <MultiSelect
                    id="staff-create-role-ids"
                    icon={ShieldIcon}
                    heading={t('staff.roles')}
                    values={form.data.role_ids.map(String)}
                    options={roles.map((role) => staffRoleCard(role, t))}
                    placeholder={t('staff.roles_placeholder')}
                    invalid={fieldState('role_ids') === 'error'}
                    onChange={(values) => {
                        setField('role_ids', values.map(Number));
                        markTouched('role_ids');
                    }}
                />
            </FormField>
        </>
    );

    const formProps = {
        onSubmit: submit,
        autoComplete: 'off' as const,
        noValidate: true,
    };

    return (
        <form {...formProps} className="relative flex min-h-0 flex-1 flex-col">
            <div aria-hidden="true" className="pointer-events-none absolute h-0 w-0 overflow-hidden opacity-0">
                <input type="text" tabIndex={-1} autoComplete="username" />
                <input type="password" tabIndex={-1} autoComplete="current-password" />
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">{fields}</div>
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} />
        </form>
    );
}
