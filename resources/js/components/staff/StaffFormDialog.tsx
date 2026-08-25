import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { UserCogIcon, UserPlusIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { StaffCreateForm } from '@/components/staff/StaffCreateForm';
import { StaffForm, emptyStaffForm, type StaffFormValues, type StaffRoleOption } from '@/components/staff/StaffForm';
import { useTranslation } from '@/hooks/useTranslation';

export type StaffFormMember = {
    id: number;
    name: string;
    email: string;
    status: string;
    role_ids?: number[];
    roles: { id: number; name: string }[];
};

type StaffFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: StaffRoleOption[];
    staff: StaffFormMember | null;
};

const modalVisit = {
    headers: { 'X-Modal': '1' },
    preserveScroll: true,
};

export function StaffFormDialog({ open, onOpenChange, roles, staff }: StaffFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = staff !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('staff.edit') : t('staff.create')}
            description={isEdit ? t('staff.edit_description') : t('staff.create_description')}
            icon={isEdit ? UserCogIcon : UserPlusIcon}
        >
            {open ? (
                <StaffFormDialogBody
                    key={staff ? `edit-${staff.id}` : 'create'}
                    roles={roles}
                    staff={staff}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function StaffFormDialogBody({
    roles,
    staff,
    onClose,
}: {
    roles: StaffRoleOption[];
    staff: StaffFormMember | null;
    onClose: () => void;
}) {
    const isEdit = staff !== null;
    const form = useForm<StaffFormValues>(
        staff
            ? {
                  name: staff.name,
                  email: staff.email,
                  password: '',
                  password_confirmation: '',
                  status: staff.status,
                  role_ids: staff.role_ids ?? staff.roles.map((role) => role.id),
              }
            : emptyStaffForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...modalVisit,
            onSuccess: onClose,
        };

        if (isEdit && staff) {
            form.put(`/staff/${staff.id}`, options);

            return;
        }

        form.post('/staff', options);
    };

    return isEdit ? (
        <StaffForm
            form={form}
            roles={roles}
            onSubmit={submit}
            onCancel={onClose}
            passwordRequired={false}
            mode="edit"
        />
    ) : (
        <StaffCreateForm
            form={form}
            roles={roles}
            onSubmit={submit}
            onCancel={onClose}
            mode="create"
        />
    );
}
