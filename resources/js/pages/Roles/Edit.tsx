import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormPage } from '@/components/FormPage';
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
            <FormPage width="lg">
                <PageHeader title={t('staff.edit_role')} description={t('staff.edit_role_description')} />
                <RoleForm form={form} matrix={matrix} onSubmit={submit} cancelHref="/roles" locked={role.is_locked} mode="edit" />
            </FormPage>
        </>
    );
}
