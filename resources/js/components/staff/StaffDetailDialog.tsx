import { SquarePenIcon, UserIcon, XIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import type { StaffFormMember } from '@/components/staff/StaffFormDialog';
import { StaffViewFields } from '@/components/staff/StaffViewFields';
import { Button } from '@/components/ui/button';
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

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={staff?.username ?? t('staff.detail')}
            description={t('staff.detail_description')}
            icon={UserIcon}
        >
            {open && staff ? (
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <StaffViewFields staff={staff} />
                        </div>
                    </div>
                    <div className={formActionBarClass}>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className={formActionButtonClass}
                            onClick={() => onOpenChange(false)}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.85} />
                            {t('common.close')}
                        </Button>
                        {can('staff.update') && onEdit ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className={formActionSubmitClass}
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
