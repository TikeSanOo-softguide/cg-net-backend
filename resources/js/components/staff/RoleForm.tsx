import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ShieldIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { PermissionMatrix, type PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { roleFormSuccessMessage, validateRoleForm, validateRoleFormField } from '@/lib/role-form-validation';

export type RoleFormValues = {
    name: string;
    permissions: string[];
};

type RoleFormProps = {
    form: InertiaFormProps<RoleFormValues>;
    matrix: PermissionMatrixGroup[];
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    locked?: boolean;
    mode?: 'create' | 'edit';
};

type TouchedFields = Record<keyof RoleFormValues, boolean>;

const untouched: TouchedFields = {
    name: false,
    permissions: false,
};

export function RoleForm({
    form,
    matrix,
    onSubmit,
    onCancel,
    locked = false,
    mode = 'create',
}: RoleFormProps) {
    const { t } = useTranslation();
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof RoleFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof RoleFormValues>(field: K, value: RoleFormValues[K]) => {
        form.setData(field, value);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof RoleFormValues): 'idle' | 'error' | 'success' => {
        if (! touched[field] && ! submitted) {
            return 'idle';
        }

        const serverError = form.errors[field];
        const clientError = validateRoleFormField(field, form.data, t);

        if (serverError || clientError) {
            return 'error';
        }

        return 'success';
    };

    const fieldError = (field: keyof RoleFormValues): string | undefined => {
        if (! touched[field] && ! submitted) {
            return undefined;
        }

        return form.errors[field] || validateRoleFormField(field, form.data, t);
    };

    const fieldSuccess = (field: keyof RoleFormValues): string | undefined => {
        return fieldState(field) === 'success' ? roleFormSuccessMessage(field, t) : undefined;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({ name: true, permissions: true });

        if (locked) {
            onSubmit(event);

            return;
        }

        const errors = validateRoleForm(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    return (
        <form onSubmit={submit} noValidate className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5">
                    <FormField
                        label={t('staff.role_name')}
                        htmlFor="name"
                        error={fieldError('name')}
                        success={fieldSuccess('name')}
                        icon={ShieldIcon}
                        required
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            disabled={locked}
                            aria-invalid={fieldState('name') === 'error'}
                            className={formControlStateClass(fieldState('name'))}
                            onBlur={() => markTouched('name')}
                            onChange={(event) => setField('name', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('staff.permissions')}
                        htmlFor="role-permissions"
                        error={fieldError('permissions')}
                        success={fieldSuccess('permissions')}
                        required
                    >
                        <PermissionMatrix
                            groups={matrix}
                            value={form.data.permissions}
                            locked={locked}
                            onChange={(permissions) => {
                                setField('permissions', permissions);
                                markTouched('permissions');
                            }}
                        />
                    </FormField>
                </div>
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} />
        </form>
    );
}
