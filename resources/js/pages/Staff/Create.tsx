import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormPage } from '@/components/FormPage';
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
                <p className="text-xs font-medium text-primary/70">{t('menu.staff_accounts')}</p>
                <StaffCreateForm
                    form={form}
                    roles={roles}
                    onSubmit={submit}
                    cancelHref="/staff"
                    title={t('staff.create')}
                    description={t('staff.create_description')}
                />
            </FormPage>
        </>
    );
}
