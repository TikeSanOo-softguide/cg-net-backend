import type { ServiceFormValues } from '@/components/cms/service/ServiceForm';

type Translate = (key: string) => string;

export const SERVICE_TITLE_MAX_LENGTH = 255;
export const SERVICE_IMAGE_MAX_SIZE_KB = 5120;
export const SERVICE_IMAGE_ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/svg+xml',
    'image/webp',
];

const SERVICE_STATUSES = ['draft', 'published', 'archived'] as const;
export type ServiceStatus = (typeof SERVICE_STATUSES)[number];

export function validateServiceField(
    field: keyof ServiceFormValues,
    data: ServiceFormValues,
    t: Translate,
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'title_en':
        case 'title_zh':
        case 'title_my':
        case 'slug': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();

            if (trimmed === '') {
                return t(`cms.service.validation.${field}_required`);
            }

            if (trimmed.length > SERVICE_TITLE_MAX_LENGTH) {
                return t(`cms.service.validation.${field}_max`);
            }
            break;
        }

        case 'description_en':
        case 'description_zh':
        case 'description_my': {
            if (typeof value !== 'string') break;
            if (value.trim() === '') {
                return t(`cms.service.validation.${field}_required`);
            }
            break;
        }

        case 'status': {
            const isValid = typeof value === 'string' && (SERVICE_STATUSES as readonly string[]).includes(value);
            if (!isValid) {
                return t('cms.service.validation.status_required');
            }
            break;
        }

        case 'image': {
            if (value === null || value === undefined || value === '') break;
            if (!(value instanceof File)) break;

            if (!SERVICE_IMAGE_ACCEPTED_TYPES.includes(value.type)) {
                return t('cms.service.validation.image_invalid_type');
            }

            const maxSizeBytes = SERVICE_IMAGE_MAX_SIZE_KB * 1024;
            if (value.size > maxSizeBytes) {
                return t('cms.service.validation.image_max_size');
            }
            break;
        }
    }

    return undefined;
}

export function validateService(
    data: ServiceFormValues,
    t: Translate,
): Partial<Record<keyof ServiceFormValues, string>> {
    const errors: Partial<Record<keyof ServiceFormValues, string>> = {};

    (Object.keys(data) as (keyof ServiceFormValues)[]).forEach((field) => {
        const message = validateServiceField(field, data, t);
        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
