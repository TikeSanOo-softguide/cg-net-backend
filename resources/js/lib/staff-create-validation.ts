import type { StaffFormValues } from '@/components/staff/StaffForm';

type Translate = (key: string) => string;

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function validateStaffCreateField(
    field: keyof StaffFormValues,
    data: StaffFormValues,
    t: Translate,
): string | undefined {
    const name = data.name.trim();
    const email = data.email.trim();

    switch (field) {
        case 'name':
            if (name === '') {
                return t('staff.validation.name_required');
            }

            if (name.length < 2) {
                return t('staff.validation.name_min');
            }

            if (name.length > 255) {
                return t('staff.validation.name_max');
            }

            return undefined;
        case 'email':
            if (email === '') {
                return t('staff.validation.email_required');
            }

            if (! emailPattern.test(email) || email.length > 255) {
                return t('staff.validation.email_invalid');
            }

            return undefined;
        case 'status':
            if (data.status !== 'active' && data.status !== 'inactive') {
                return t('staff.validation.status_required');
            }

            return undefined;
        case 'password':
            if (data.password === '') {
                return t('staff.validation.password_required');
            }

            if (data.password.length < 8) {
                return t('staff.validation.password_min');
            }

            return undefined;
        case 'password_confirmation':
            if (data.password_confirmation === '') {
                return t('staff.validation.password_confirmation_required');
            }

            if (data.password_confirmation !== data.password) {
                return t('staff.validation.password_confirmation_mismatch');
            }

            return undefined;
        case 'role_ids':
            if (data.role_ids.length < 1) {
                return t('staff.validation.roles_required');
            }

            return undefined;
        default:
            return undefined;
    }
}

export function validateStaffCreate(data: StaffFormValues, t: Translate): Partial<Record<keyof StaffFormValues, string>> {
    const errors: Partial<Record<keyof StaffFormValues, string>> = {};

    (Object.keys(data) as (keyof StaffFormValues)[]).forEach((field) => {
        const message = validateStaffCreateField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function staffCreateSuccessMessage(field: keyof StaffFormValues, t: Translate): string {
    switch (field) {
        case 'name':
            return t('staff.validation.name_ok');
        case 'email':
            return t('staff.validation.email_ok');
        case 'status':
            return t('staff.validation.status_ok');
        case 'password':
            return t('staff.validation.password_ok');
        case 'password_confirmation':
            return t('staff.validation.password_confirmation_ok');
        case 'role_ids':
            return t('staff.validation.roles_ok');
        default:
            return '';
    }
}
