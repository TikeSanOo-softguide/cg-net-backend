import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { PageHeader } from '@/components/PageHeader';
import { StaffForm, type StaffFormValues, type StaffRoleOption } from '@/components/staff/StaffForm';
import { useTranslation } from '@/hooks/useTranslation';

type StaffCreateProps = {
    roles: StaffRoleOption[];
};

export default function StaffCreate({ roles }: StaffCreateProps) {
    const { t } = useTranslation();
    const form = useForm<StaffFormValues>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        status: 'active',
        role_ids: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/staff');
    };

    return (
        <>
            <Head title={t('staff.create')} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader eyebrow={t('menu.staff_accounts')} title={t('staff.create')} description={t('staff.create_description')} />
                <StaffForm form={form} roles={roles} onSubmit={submit} cancelHref="/staff" />
            </div>
        </>
    );
}
