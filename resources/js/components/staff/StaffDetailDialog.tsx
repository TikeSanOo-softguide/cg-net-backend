import {
    CalendarIcon,
    CircleDotIcon,
    MailIcon,
    ShieldIcon,
    SquarePenIcon,
    UserIcon,
    XIcon,
} from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StatusBadge } from '@/components/StatusBadge';
import type { StaffFormMember } from '@/components/staff/StaffFormDialog';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

export type StaffDetailMember = StaffFormMember & {
    created_at?: string | null;
};

type StaffDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    staff: StaffDetailMember | null;
    onEdit?: (staff: StaffDetailMember) => void;
};

export function StaffDetailDialog({ open, onOpenChange, staff, onEdit }: StaffDetailDialogProps) {
    const { t } = useTranslation();
    const can = useCan();
    const roleNames = staff?.roles.map((role) => role.name).join(', ') || '—';

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={staff?.name ?? t('staff.detail')}
            description={t('staff.detail_description')}
            icon={UserIcon}
        >
            {open && staff ? (
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                            <FormField label={t('staff.name')} htmlFor="view-staff-name" icon={UserIcon} className="sm:col-span-2">
                                <Input id="view-staff-name" value={staff.name} readOnly />
                            </FormField>
                            <FormField label={t('staff.email')} htmlFor="view-staff-email" icon={MailIcon}>
                                <Input id="view-staff-email" value={staff.email} readOnly />
                            </FormField>
                            <FormField label={t('common.status')} htmlFor="view-staff-status" icon={CircleDotIcon}>
                                <div id="view-staff-status" className="flex h-10 items-center">
                                    <StatusBadge status={staff.status} />
                                </div>
                            </FormField>
                            <FormField label={t('customers.joined')} htmlFor="view-staff-joined" icon={CalendarIcon} className="sm:col-span-2">
                                <Input id="view-staff-joined" value={staff.created_at ?? '—'} readOnly />
                            </FormField>
                            <FormField label={t('staff.roles')} htmlFor="view-staff-roles" icon={ShieldIcon} className="sm:col-span-2">
                                <Input id="view-staff-roles" value={roleNames} readOnly />
                            </FormField>
                        </div>
                    </div>
                    <div className="flex w-full shrink-0 items-center justify-center gap-2 border-t border-border/70 px-4 py-3 sm:px-5 sm:py-4">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-8 w-[120px] shrink-0 rounded-[4px]"
                            onClick={() => onOpenChange(false)}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.85} />
                            {t('common.close')}
                        </Button>
                        {can('staff.update') && onEdit ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="primary"
                                className="h-8 w-[120px] shrink-0 rounded-[4px]"
                                onClick={() => onEdit(staff)}
                            >
                                <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                                {t('common.edit')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
