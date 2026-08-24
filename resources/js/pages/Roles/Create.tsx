import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PageHeader } from '@/components/PageHeader';
import { RoleForm, type RoleFormValues } from '@/components/staff/RoleForm';
import type { PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { useTranslation } from '@/hooks/useTranslation';

type RoleCreateProps = {
    matrix: PermissionMatrixGroup[];
};

export default function RoleCreate({ matrix }: RoleCreateProps) {
    const { t } = useTranslation();
    const form = useForm<RoleFormValues>({
        name: '',
        permissions: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/roles');
    };

    return (
        <>
            <Head title={t('staff.create_role')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.roles')} title={t('staff.create_role')} description={t('staff.role_form_description')} />
                <RoleForm form={form} matrix={matrix} onSubmit={submit} cancelHref="/roles" />
            </div>
        </>
    );
}
