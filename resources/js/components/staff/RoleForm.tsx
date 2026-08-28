import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ShieldIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { PermissionMatrix, type PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

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

export function RoleForm({
    form,
    matrix,
    onSubmit,
    onCancel,
    locked = false,
    mode = 'create',
}: RoleFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="shrink-0 px-4 pt-4 sm:px-5 sm:pt-5">
                <FormField label={t('staff.role_name')} htmlFor="name" error={form.errors.name} icon={ShieldIcon} required>
                    <Input
                        id="name"
                        value={form.data.name}
                        required
                        disabled={locked}
                        aria-invalid={Boolean(form.errors.name)}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                </FormField>
                {form.errors.permissions ? <p className="mt-2 text-xs text-danger">{form.errors.permissions}</p> : null}
            </div>
            <div className="form-scroll mt-4 min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4 sm:px-5">
                <PermissionMatrix
                    groups={matrix}
                    value={form.data.permissions}
                    locked={locked}
                    onChange={(permissions) => form.setData('permissions', permissions)}
                />
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} />
        </form>
    );
}
