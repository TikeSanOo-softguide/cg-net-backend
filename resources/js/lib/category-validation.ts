import type { CategoryFormValues } from '@/components/cms/category/CategoryForm';

type Translate = (key: string) => string;

export const CATEGORY_NAME_MAX_LENGTH = 50;
export const CATEGORY_SLUG_MAX_LENGTH = 50;

export function getCategoryValidationError(
    error: string | undefined,
    field: keyof CategoryFormValues,
    t: Translate,
): string | undefined {
    if (!error) {
        return undefined;
    }

    const message = error.toLowerCase();
    let rule: string | undefined;

    if (message.includes('required')) {
        rule = 'required';
    } else if (message.includes('unique') || message.includes('already been taken')) {
        rule = 'unique';
    } else if (message.includes('string')) {
        rule = 'string';
    } else if (
        message.includes('may not be greater than') ||
        message.includes('must not be greater than') ||
        message.includes('maximum')
    ) {
        rule = 'max';
    }

    return rule ? t(`cms.category.validation.${field}_${rule}`) : error;
}

export function validateCategoryField(
    field: keyof CategoryFormValues,
    data: CategoryFormValues,
    t: Translate,
): string | undefined {
    const value = data[field].trim();

    if (value === '') {
        return t(`cms.category.validation.${field}_required`);
    }

    const maxLength = field === 'slug' ? CATEGORY_SLUG_MAX_LENGTH : CATEGORY_NAME_MAX_LENGTH;

    if (value.length > maxLength) {
        return t(`cms.category.validation.${field}_max`);
    }

    return undefined;
}

export function validateCategory(
    data: CategoryFormValues,
    t: Translate,
): Partial<Record<keyof CategoryFormValues, string>> {
    const errors: Partial<Record<keyof CategoryFormValues, string>> = {};

    (Object.keys(data) as (keyof CategoryFormValues)[]).forEach((field) => {
        const message = validateCategoryField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
