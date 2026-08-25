import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { MailIcon, ShieldIcon, SquarePenIcon, UserIcon } from 'lucide-react';

import { PageContent } from '@/components/PageContent';
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
            <PageContent>
                <PageHeader
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
                <div className="mx-auto grid w-full max-w-4xl grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="flex flex-row items-center gap-3 px-5 py-5">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <UserIcon className="size-[22px]" strokeWidth={1.85} />
                            </div>
                            <CardTitle className="text-lg font-semibold">{t('staff.profile')}</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 px-5 py-5 sm:px-6">
                            <div className="flex items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-muted text-foreground">
                                    <UserIcon className="size-5" strokeWidth={1.85} />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold">{staffMember.name}</p>
                                    <p className="text-xs text-muted-foreground">{t('staff.name')}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-muted text-foreground">
                                    <MailIcon className="size-5" strokeWidth={1.85} />
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
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="flex flex-row items-center gap-3 px-5 py-5">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <ShieldIcon className="size-[22px]" strokeWidth={1.85} />
                            </div>
                            <CardTitle className="text-lg font-semibold">{t('staff.assigned_roles')}</CardTitle>
                        </CardHeader>
                        <CardContent className="px-5 py-5 sm:px-6">
                            {can('staff.update') ? (
                                <form onSubmit={submitRoles} className="flex flex-col gap-4">
                                    <FormField label={t('staff.roles')} htmlFor="role_ids" error={form.errors.role_ids} required>
                                        <MultiSelect
                                            id="role_ids"
                                            icon={ShieldIcon}
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
            </PageContent>
        </>
    );
}
