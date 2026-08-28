import { AtSignIcon, CalendarIcon, ShieldIcon } from 'lucide-react';

import { StaffRoleChip } from '@/components/staff/StaffRoleChip';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type StaffViewMember = {
    username: string;
    status: string;
    roles: { id: number; name: string }[];
    created_at?: string | null;
};

export function StaffViewFields({ staff }: { staff: StaffViewMember }) {
    const { t } = useTranslation();

    return (
        <>
            <FormField label={t('staff.username')} htmlFor="view-username" icon={AtSignIcon}>
                <Input id="view-username" value={staff.username} readOnly />
            </FormField>
            <FormField label={t('staff.roles')} htmlFor="view-roles" icon={ShieldIcon}>
                <div
                    id="view-roles"
                    data-slot="input"
                    className={cn(formControlClass, 'flex min-h-10 h-auto flex-wrap items-center gap-1.5 py-1.5')}
                >
                    {staff.roles.length === 0 ? (
                        <span className="text-muted-foreground">—</span>
                    ) : (
                        staff.roles.map((role) => <StaffRoleChip key={role.id} name={role.name} />)
                    )}
                </div>
            </FormField>
            <FormField label={t('customers.joined')} htmlFor="view-joined" icon={CalendarIcon}>
                <Input id="view-joined" value={staff.created_at ?? '—'} readOnly />
            </FormField>
            <FormField label={t('common.status')} htmlFor="view-status">
                <StaffStatusSwitch id="view-status" value={staff.status} readOnly />
            </FormField>
        </>
    );
}
