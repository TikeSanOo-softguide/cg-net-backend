import type { PromotionFormValues } from '@/components/cms/promotion/PromotionForm';

type Translate = (key: string) => string;

export const PROMOTION_TITLE_MAX_LENGTH = 255;
export const PROMOTION_DESCRIPTION_MAX_LENGTH = 5000;
export const PROMOTION_IMAGE_MAX_SIZE_KB = 5120; // 5MB

export const PROMOTION_IMAGE_ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/svg+xml',
    'image/webp',
];

type PromotionField = keyof PromotionFormValues;

export type PromotionValidationErrors = Partial<Record<PromotionField, string>>;

export function validatePromotionField(
    field: PromotionField,
    data: PromotionFormValues,
    t: Translate,
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'title_en':
        case 'title_my':
        case 'title_zh':
        case 'slug': {
            if (typeof value !== 'string') break;

            const trimmed = value.trim();

            if (trimmed === '') {
                return t(`cms.promotions.validation.${field}_required`);
            }

            if (trimmed.length > PROMOTION_TITLE_MAX_LENGTH) {
                return t(`cms.promotions.validation.${field}_max`);
            }

            break;
        }

        case 'description_en':
        case 'description_my':
        case 'description_zh': {
            if (typeof value !== 'string') break;

            const trimmed = value.trim();

            if (trimmed === '') {
                return t(`cms.promotions.validation.${field}_required`);
            }

            if (trimmed.length > PROMOTION_DESCRIPTION_MAX_LENGTH) {
                return t(`cms.promotions.validation.${field}_max`);
            }

            break;
        }

        case 'start_date': {
            // nullable
            if (value === null || value === undefined || value === '') {
                break;
            }

            if (typeof value === 'string' && !isValidDate(value)) {
                return t('cms.promotions.validation.start_date');
            }

            break;
        }

        case 'end_date': {
            // nullable
            if (value === null || value === undefined || value === '') {
                break;
            }

            if (typeof value === 'string' && !isValidDate(value)) {
                return t('cms.promotions.validation.end_date');
            }

            if (
                typeof value === 'string' &&
                typeof data.start_date === 'string' &&
                data.start_date !== '' &&
                value < data.start_date
            ) {
                return t('cms.promotions.validation.end_date_after_or_equal');
            }

            break;
        }

        case 'is_active': {
            if (typeof value !== 'boolean') {
                return t('cms.promotions.validation.is_active_required');
            }

            break;
        }

        case 'image': {
            // Not required (nullable) — skip if empty
            if (value === null || value === undefined || value === '') break;

            if (!(value instanceof File)) break; // already-set string URL (existing image), nothing to validate

            if (!PROMOTION_IMAGE_ACCEPTED_TYPES.includes(value.type)) {
                return t('cms.news.validation.image_invalid_type');
            }

            const maxSizeBytes = PROMOTION_IMAGE_MAX_SIZE_KB * 1024;
            if (value.size > maxSizeBytes) {
                return t('cms.news.validation.image_max_size');
            }
            break;
        }
    }

    return undefined;
}

export function validatePromotion(data: PromotionFormValues, t: Translate): PromotionValidationErrors {
    const errors: PromotionValidationErrors = {};

    (Object.keys(data) as PromotionField[]).forEach((field) => {
        const message = validatePromotionField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

function isValidDate(value: string): boolean {
    const date = new Date(`${value}T00:00:00`);
    return !Number.isNaN(date.getTime());
}
