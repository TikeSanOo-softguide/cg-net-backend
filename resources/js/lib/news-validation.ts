import type { NewsFormValues } from '@/components/cms/news/NewsForm';

type Translate = (key: string) => string;

export const NEWS_TITLE_MAX_LENGTH = 255;
export const NEWS_IMAGE_MAX_SIZE_KB = 5120; // 5MB, matches File::image()->max(5120)
export const NEWS_IMAGE_ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/svg+xml',
    'image/webp',
];

const NEWS_STATUSES = ['draft', 'published', 'archived'] as const;
export type NewsStatus = (typeof NEWS_STATUSES)[number];

export function validateNewsField(field: keyof NewsFormValues, data: NewsFormValues, t: Translate): string | undefined {
    const value = data[field];

    switch (field) {
        case 'category_id': {
            if (value === null || value === undefined || value === '') {
                return t('cms.news.validation.category_id_required');
            }
            break;
        }

        case 'title_en':
        case 'title_zh':
        case 'title_my':
        case 'slug': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();

            if (trimmed === '') {
                return t(`cms.news.validation.${field}_required`);
            }
            if (trimmed.length > NEWS_TITLE_MAX_LENGTH) {
                return t(`cms.news.validation.${field}_max`);
            }
            break;
        }

        case 'description_en':
        case 'description_zh':
        case 'description_my': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();

            if (trimmed === '') {
                return t(`cms.news.validation.${field}_required`);
            }
            break;
        }

        case 'status': {
            const isValid = typeof value === 'string' && (NEWS_STATUSES as readonly string[]).includes(value);

            if (!isValid) {
                return t('cms.news.validation.status_required');
            }
            break;
        }

        case 'image': {
            // Not required (nullable) — skip if empty
            if (value === null || value === undefined || value === '') break;

            if (!(value instanceof File)) break; // already-set string URL (existing image), nothing to validate

            if (!NEWS_IMAGE_ACCEPTED_TYPES.includes(value.type)) {
                return t('cms.news.validation.image_invalid_type');
            }

            const maxSizeBytes = NEWS_IMAGE_MAX_SIZE_KB * 1024;
            if (value.size > maxSizeBytes) {
                return t('cms.news.validation.image_max_size');
            }
            break;
        }
    }

    return undefined;
}

export function validateNews(data: NewsFormValues, t: Translate): Partial<Record<keyof NewsFormValues, string>> {
    const errors: Partial<Record<keyof NewsFormValues, string>> = {};

    (Object.keys(data) as (keyof NewsFormValues)[]).forEach((field) => {
        const message = validateNewsField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function newsSuccessMessage(field: keyof NewsFormValues, t: Translate): string {
    return t(`cms.news.validation.${field}_ok`);
}
