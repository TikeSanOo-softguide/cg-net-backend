import type { RoleFormValues } from '@/components/staff/RoleForm';

type Translate = (key: string) => string;

export function validateRoleFormField(
    field: keyof RoleFormValues,
    data: RoleFormValues,
    t: Translate,
): string | undefined {
    switch (field) {
        case 'name': {
            const name = data.name.trim();

            if (name === '') {
                return t('staff.validation.name_required');
            }

            if (name.length < 2) {
                return t('staff.validation.name_min');
            }

            if (name.length > 80) {
                return t('staff.validation.name_max');
            }

            return undefined;
        }
        case 'permissions':
            if (data.permissions.length < 1) {
                return t('staff.validation.permissions_required');
            }

            return undefined;
        default:
            return undefined;
    }
}

export function validateRoleForm(data: RoleFormValues, t: Translate): Partial<Record<keyof RoleFormValues, string>> {
    const errors: Partial<Record<keyof RoleFormValues, string>> = {};

    (Object.keys(data) as (keyof RoleFormValues)[]).forEach((field) => {
        const message = validateRoleFormField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function roleFormSuccessMessage(field: keyof RoleFormValues, t: Translate): string {
    switch (field) {
        case 'name':
            return t('staff.validation.name_ok');
        case 'permissions':
            return t('staff.validation.permissions_ok');
        default:
            return '';
    }
}
