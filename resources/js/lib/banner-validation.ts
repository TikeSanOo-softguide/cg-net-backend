import type { BannerFormValues } from '@/components/cms/banner/BannerForm';

type Translate = (key: string) => string;

export const BANNER_IMAGE_MAX_SIZE_KB = 5120;
export const BANNER_IMAGE_ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/svg+xml',
    'image/webp',
];

const bannerImageFieldMessageMap = {
    image_url_en: 'image_en_required',
    image_url_zh: 'image_zh_required',
    image_url_my: 'image_my_required',
} as const;

export function validateBannerField(
    field: keyof BannerFormValues,
    data: BannerFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
    hasExistingImage = false,
): string | undefined {
    const value = data[field];

    if (field === 'image_url_en' || field === 'image_url_zh' || field === 'image_url_my') {
        if (mode === 'edit' && hasExistingImage && !value) {
            return undefined;
        }

        if (!value) {
            return t(`cms.banner.validation.${bannerImageFieldMessageMap[field]}`);
        }

        if (!(value instanceof File)) {
            return t(`cms.banner.validation.${bannerImageFieldMessageMap[field]}`);
        }

        const maxSizeBytes = BANNER_IMAGE_MAX_SIZE_KB * 1024;
        if (value.size > maxSizeBytes) {
            return t('cms.gallery.validation.image_max_size');
        }

        if (!BANNER_IMAGE_ACCEPTED_TYPES.includes(value.type)) {
            return t('cms.gallery.validation.image_invalid_type');
        }

        return undefined;
    }

    if (field === 'sort_order') {
        if (!Number.isFinite(value)) {
            return t('validation.integer');
        }

        return undefined;
    }

    if (field === 'is_active') {
        if (typeof value !== 'boolean') {
            return t('validation.boolean');
        }

        return undefined;
    }

    if (field === 'start_date' || field === 'end_date') {
        if (value === '') {
            return undefined;
        }

        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return t('validation.date');
        }
    }

    return undefined;
}

export function validateBanner(
    data: BannerFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
    existingImages: Partial<Record<'image_url_en' | 'image_url_zh' | 'image_url_my', boolean>> = {},
): Partial<Record<keyof BannerFormValues, string>> {
    const errors: Partial<Record<keyof BannerFormValues, string>> = {};

    (Object.keys(data) as (keyof BannerFormValues)[]).forEach((field) => {
        const existingImage =
            field === 'image_url_en' || field === 'image_url_zh' || field === 'image_url_my'
                ? (existingImages[field] ?? false)
                : false;

        const message = validateBannerField(field, data, t, mode, existingImage);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function bannerSuccessMessage(field: keyof BannerFormValues, t: Translate): string {
    return t(`cms.banner.validation.${field}_ok`);
}
