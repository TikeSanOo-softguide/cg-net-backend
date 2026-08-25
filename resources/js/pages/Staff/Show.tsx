import { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    CalendarIcon,
    CircleDotIcon,
    MailIcon,
    ShieldIcon,
    SquarePenIcon,
    UserIcon,
} from 'lucide-react';

import { DetailPanel } from '@/components/DetailPanel';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { StaffFormDialog } from '@/components/staff/StaffFormDialog';
import type { StaffRoleOption } from '@/components/staff/StaffForm';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

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
    const [formOpen, setFormOpen] = useState(false);
    const roleNames = staffMember.roles.map((role) => role.name).join(', ') || '—';

    return (
        <>
            <Head title={staffMember.name} />
            <PageContent>
                <PageHeader title={staffMember.name} description={t('staff.detail_description')} />
                <div className="mx-auto w-full max-w-[560px]">
                    <DetailPanel
                        title={t('staff.detail')}
                        description={t('staff.detail_description')}
                        icon={UserIcon}
                        actions={
                            can('staff.update') ? (
                                <Button type="button" size="sm" variant="outline" onClick={() => setFormOpen(true)}>
                                    <SquarePenIcon />
                                    {t('common.edit')}
                                </Button>
                            ) : null
                        }
                        footer={
                            can('staff.update') ? (
                                <Button type="button" size="sm" variant="primary" className="h-8 w-[120px] rounded-[4px]" onClick={() => setFormOpen(true)}>
                                    <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                                    {t('common.edit')}
                                </Button>
                            ) : null
                        }
                    >
                        <FormField label={t('staff.name')} htmlFor="detail-name" icon={UserIcon} className="sm:col-span-2">
                            <Input id="detail-name" value={staffMember.name} readOnly />
                        </FormField>
                        <FormField label={t('staff.email')} htmlFor="detail-email" icon={MailIcon}>
                            <Input id="detail-email" value={staffMember.email} readOnly />
                        </FormField>
                        <FormField label={t('common.status')} htmlFor="detail-status" icon={CircleDotIcon}>
                            <div id="detail-status" className="flex h-10 items-center">
                                <StatusBadge status={staffMember.status} />
                            </div>
                        </FormField>
                        <FormField label={t('customers.joined')} htmlFor="detail-joined" icon={CalendarIcon} className="sm:col-span-2">
                            <Input id="detail-joined" value={staffMember.created_at ?? '—'} readOnly />
                        </FormField>
                        <FormField label={t('staff.roles')} htmlFor="detail-roles" icon={ShieldIcon} className="sm:col-span-2">
                            <Input id="detail-roles" value={roleNames} readOnly />
                        </FormField>
                    </DetailPanel>
                </div>
            </PageContent>
            <StaffFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                roles={roles}
                staff={staffMember}
            />
        </>
    );
}
