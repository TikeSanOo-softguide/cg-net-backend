import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { MailIcon, SquarePenIcon, UserIcon } from 'lucide-react';

import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { MultiSelect } from '@/components/MultiSelect';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import type { StaffRoleOption } from '@/components/staff/StaffForm';

type StaffMember = {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: StaffRoleOption[];
    role_ids: number[];
    created_at: string | null;
};

type StaffShowProps = {
    staffMember: StaffMember;
    roles: StaffRoleOption[];
};

export default function StaffShow({ staffMember, roles }: StaffShowProps) {
    const { t } = useTranslation();
    const can = useCan();
    const form = useForm({
        name: staffMember.name,
        email: staffMember.email,
        status: staffMember.status,
        role_ids: staffMember.role_ids,
        password: '',
        password_confirmation: '',
    });

    const submitRoles = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/staff/${staffMember.id}`);
    };

    return (
        <>
            <Head title={staffMember.name} />
            <div className="flex w-full flex-col gap-5 pt-6 lg:pt-8">
                <PageHeader
                    eyebrow={t('menu.staff_accounts')}
                    title={staffMember.name}
                    description={t('staff.detail_description')}
                    actions={
                        can('staff.update') ? (
                            <Button asChild>
                                <Link href={`/staff/${staffMember.id}/edit`}>
                                    <SquarePenIcon />
                                    {t('common.edit')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border/70 px-4 py-4 sm:px-5">
                            <CardTitle>{t('staff.profile')}</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 px-4 py-4 sm:px-5">
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-full bg-primary/15 text-primary">
                                    <UserIcon className="size-4" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold">{staffMember.name}</p>
                                    <p className="text-xs text-muted-foreground">{t('staff.name')}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-full bg-primary/15 text-primary">
                                    <MailIcon className="size-4" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold">{staffMember.email}</p>
                                    <p className="text-xs text-muted-foreground">{t('staff.email')}</p>
                                </div>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm text-muted-foreground">{t('common.status')}</span>
                                <StatusBadge status={staffMember.status} />
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm text-muted-foreground">{t('customers.joined')}</span>
                                <span className="text-sm font-medium">{staffMember.created_at}</span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b border-border/70 px-4 py-4 sm:px-5">
                            <CardTitle>{t('staff.assigned_roles')}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 py-4 sm:px-5">
                            {can('staff.update') ? (
                                <form onSubmit={submitRoles} className="flex flex-col gap-4">
                                    <FormField label={t('staff.roles')} htmlFor="role_ids" error={form.errors.role_ids} required>
                                        <MultiSelect
                                            id="role_ids"
                                            values={form.data.role_ids.map(String)}
                                            options={roles.map((role) => ({ value: String(role.id), label: role.name }))}
                                            placeholder={t('staff.roles_placeholder')}
                                            invalid={Boolean(form.errors.role_ids)}
                                            onChange={(values) => form.setData('role_ids', values.map(Number))}
                                        />
                                    </FormField>
                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={form.processing}>
                                            {t('common.save')}
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {staffMember.roles.map((role) => (
                                        <span key={role.id} className="rounded-[6px] bg-primary/12 px-2 py-1 text-xs font-medium text-primary">
                                            {role.name}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
