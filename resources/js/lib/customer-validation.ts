import type { CustomerFormValues } from '@/components/customer/CustomerForm';
import { isValidAppUserPhone, parsePhone } from '@/lib/phone';

type Translate = (key: string) => string;

export function validateCustomerField(
    field: keyof CustomerFormValues,
    data: CustomerFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
): string | undefined {
    const name = data.name.trim();
    const passwordRequired = mode === 'create' || data.password.length > 0 || data.password_confirmation.length > 0;

    switch (field) {
        case 'name':
            if (name === '') {
                return t('customers.validation.name_required');
            }

            if (name.length < 2) {
                return t('customers.validation.name_min');
            }

            if (name.length > 255) {
                return t('customers.validation.name_max');
            }

            return undefined;
        case 'phone': {
            const parsed = parsePhone(data.phone);

            if (parsed.local === '' || data.phone.trim() === '') {
                return t('customers.validation.phone_required');
            }

            if (! isValidAppUserPhone(data.phone)) {
                return t('customers.validation.phone_invalid');
            }

            return undefined;
        }
        case 'status':
            if (data.status !== 'active' && data.status !== 'suspended') {
                return t('customers.validation.status_required');
            }

            return undefined;
        case 'password':
            if (! passwordRequired) {
                return undefined;
            }

            if (data.password === '') {
                return t('customers.validation.password_required');
            }

            if (data.password.length < 8) {
                return t('customers.validation.password_min');
            }

            return undefined;
        case 'password_confirmation':
            if (! passwordRequired) {
                return undefined;
            }

            if (data.password_confirmation === '') {
                return t('customers.validation.password_confirmation_required');
            }

            if (data.password_confirmation !== data.password) {
                return t('customers.validation.password_confirmation_mismatch');
            }

            return undefined;
        default:
            return undefined;
    }
}

export function validateCustomerForm(
    data: CustomerFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
): Partial<Record<keyof CustomerFormValues, string>> {
    const errors: Partial<Record<keyof CustomerFormValues, string>> = {};

    (Object.keys(data) as (keyof CustomerFormValues)[]).forEach((field) => {
        const message = validateCustomerField(field, data, t, mode);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
