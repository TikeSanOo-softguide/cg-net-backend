import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { ShieldIcon, SquarePenIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import type { PermissionMatrixGroup } from '@/components/PermissionMatrix';
import { RoleForm, type RoleFormValues } from '@/components/staff/RoleForm';
import { useTranslation } from '@/hooks/useTranslation';

export type RoleFormItem = {
    id: number;
    name: string;
    is_locked: boolean;
    permissions: string[];
};

type RoleFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    role: RoleFormItem | null;
    matrix: PermissionMatrixGroup[];
};

const modalVisit = {
    headers: { 'X-Modal': '1' },
    preserveScroll: true,
};

function emptyRoleForm(): RoleFormValues {
    return {
        name: '',
        permissions: [],
    };
}

export function RoleFormDialog({ open, onOpenChange, role, matrix }: RoleFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = role !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('staff.edit_role') : t('staff.create_role')}
            description={isEdit ? t('staff.edit_role_description') : t('staff.create_role_description')}
            icon={isEdit ? SquarePenIcon : ShieldIcon}
            className="sm:max-h-[min(90vh,820px)] sm:w-[min(100%-2rem,720px)] sm:max-w-[720px]"
        >
            {open ? (
                <RoleFormDialogBody
                    key={role ? `edit-${role.id}` : 'create'}
                    role={role}
                    matrix={matrix}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function RoleFormDialogBody({
    role,
    matrix,
    onClose,
}: {
    role: RoleFormItem | null;
    matrix: PermissionMatrixGroup[];
    onClose: () => void;
}) {
    const isEdit = role !== null;
    const form = useForm<RoleFormValues>(
        role
            ? {
                  name: role.name,
                  permissions: role.permissions,
              }
            : emptyRoleForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...modalVisit,
            onSuccess: onClose,
        };

        if (isEdit && role) {
            form.put(`/roles/${role.id}`, options);

            return;
        }

        form.post('/roles', options);
    };

    return (
        <RoleForm
            form={form}
            matrix={matrix}
            onSubmit={submit}
            onCancel={onClose}
            locked={role?.is_locked}
            mode={isEdit ? 'edit' : 'create'}
        />
    );
}
