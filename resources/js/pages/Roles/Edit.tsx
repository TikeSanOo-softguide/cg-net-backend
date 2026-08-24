import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PageHeader } from '@/components/PageHeader';
import { RoleForm, type RoleFormValues } from '@/components/staff/RoleForm';
import type { PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { useTranslation } from '@/hooks/useTranslation';

type RoleEditProps = {
    role: {
        id: number;
        name: string;
        is_locked: boolean;
        permissions: string[];
    };
    matrix: PermissionMatrixGroup[];
};

export default function RoleEdit({ role, matrix }: RoleEditProps) {
    const { t } = useTranslation();
    const form = useForm<RoleFormValues>({
        name: role.name,
        permissions: role.permissions,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/roles/${role.id}`);
    };

    return (
        <>
            <Head title={t('staff.edit_role')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.roles')} title={t('staff.edit_role')} description={t('staff.role_form_description')} />
                <RoleForm form={form} matrix={matrix} onSubmit={submit} cancelHref="/roles" locked={role.is_locked} />
            </div>
        </>
    );
}
