import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

import { FormPage } from '@/components/FormPage';
import { PageHeader } from '@/components/PageHeader';
import { StaffForm, type StaffFormValues, type StaffRoleOption } from '@/components/staff/StaffForm';
import { useTranslation } from '@/hooks/useTranslation';

type StaffMember = StaffFormValues & {
    id: number;
    roles: StaffRoleOption[];
};

type StaffEditProps = {
    staffMember: StaffMember;
    roles: StaffRoleOption[];
};

export default function StaffEdit({ staffMember, roles }: StaffEditProps) {
    const { t } = useTranslation();
    const form = useForm<StaffFormValues>({
        name: staffMember.name,
        email: staffMember.email,
        password: '',
        password_confirmation: '',
        status: staffMember.status,
        role_ids: staffMember.role_ids,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/staff/${staffMember.id}`);
    };

    return (
        <>
            <Head title={t('staff.edit')} />
            <FormPage>
                <PageHeader title={t('staff.edit')} description={t('staff.edit_description')} />
                <StaffForm
                    form={form}
                    roles={roles}
                    onSubmit={submit}
                    cancelHref={`/staff/${staffMember.id}`}
                    passwordRequired={false}
                    mode="edit"
                    title={t('staff.edit')}
                    description={t('staff.edit_description')}
                />
            </FormPage>
        </>
    );
}
