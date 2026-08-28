import type { CategoryFormValues } from '@/components/cms/category/CategoryForm';

type Translate = (key: string) => string;

export const CATEGORY_NAME_MAX_LENGTH = 100;
export const CATEGORY_SLUG_MAX_LENGTH = 100;

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

export function categorySuccessMessage(field: keyof CategoryFormValues, t: Translate): string {
    return t(`cms.category.validation.${field}_ok`);
}