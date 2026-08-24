import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ShieldIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { PermissionMatrix, type PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { Card, CardContent } from '@/components/ui/card';
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
        <Card className="max-w-4xl gap-0 py-0">
            <CardContent className="px-4 py-4 pb-24 sm:px-5 sm:py-5 sm:pb-5">
                <form onSubmit={onSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                        <p className="mb-2 text-sm font-medium text-foreground">{t('staff.permission_matrix')}</p>
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
            </CardContent>
        </Card>
    );
}
