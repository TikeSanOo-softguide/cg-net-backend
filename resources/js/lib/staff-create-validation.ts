import type { StaffFormValues } from '@/components/staff/StaffForm';

type Translate = (key: string) => string;

const usernamePattern = /^[A-Za-z][A-Za-z0-9]*(?:[ ._ -][A-Za-z0-9]+)*$/;

export function validateStaffCreateField(
    field: keyof StaffFormValues,
    data: StaffFormValues,
    t: Translate,
): string | undefined {
    const username = data.username.trim();

    switch (field) {
        case 'username':
            if (username === '') {
                return t('staff.validation.username_required');
            }

            if (username.length < 3) {
                return t('staff.validation.username_min');
            }

            if (username.length > 50 || ! usernamePattern.test(username)) {
                return t('staff.validation.username_invalid');
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
        case 'username':
            return t('staff.validation.username_ok');
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
