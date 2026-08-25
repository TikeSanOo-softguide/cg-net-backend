import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { StaffCreateForm } from '@/components/staff/StaffCreateForm';
import { type StaffFormValues, type StaffRoleOption } from '@/components/staff/StaffForm';
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
            <FormPage>
                <PageHeader title={t('staff.create')} description={t('staff.create_description')} />
                <StaffCreateForm
                    form={form}
                    roles={roles}
                    onSubmit={submit}
                    cancelHref="/staff"
                    mode="create"
                />
            </FormPage>
        </>
    );
}
