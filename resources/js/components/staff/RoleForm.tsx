import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ShieldIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormCard } from '@/components/FormCard';
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
    cancelHref: string;
    locked?: boolean;
};

export function RoleForm({ form, matrix, onSubmit, cancelHref, locked = false }: RoleFormProps) {
    const { t } = useTranslation();

    return (
        <FormCard title={t('staff.permission_matrix')} icon={ShieldIcon}>
            <form onSubmit={onSubmit} className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <FormField label={t('staff.role_name')} htmlFor="name" error={form.errors.name} icon={ShieldIcon} required className="sm:col-span-2">
                    <Input
                        id="name"
                        value={form.data.name}
                        required
                        disabled={locked}
                        aria-invalid={Boolean(form.errors.name)}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                </FormField>
                <div className="sm:col-span-2">
                    {form.errors.permissions ? <p className="mb-2 text-xs text-danger">{form.errors.permissions}</p> : null}
                    <PermissionMatrix
                        groups={matrix}
                        value={form.data.permissions}
                        locked={locked}
                        onChange={(permissions) => form.setData('permissions', permissions)}
                    />
                </div>
                <FormActionBar cancelHref={cancelHref} processing={form.processing} className="sm:col-span-2" />
            </form>
        </FormCard>
    );
}
