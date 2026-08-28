import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { SquarePenIcon, UserIcon } from 'lucide-react';

import { DetailPanel } from '@/components/DetailPanel';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StaffFormDialog } from '@/components/staff/StaffFormDialog';
import { StaffViewFields } from '@/components/staff/StaffViewFields';
import type { StaffRoleOption } from '@/components/staff/StaffForm';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type StaffMember = {
    id: number;
    username: string;
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

    return (
        <>
            <Head title={staffMember.username} />
            <PageContent>
                <PageHeader title={staffMember.username} description={t('staff.detail_description')} />
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
                        <StaffViewFields staff={staffMember} />
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
