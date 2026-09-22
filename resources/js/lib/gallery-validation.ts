import type { GalleryFormValues } from '@/components/cms/gallery/GalleryForm';

type Translate = (key: string) => string;

export const GALLERY_LABEL_MAX_LENGTH = 255;
export const GALLERY_IMAGE_MAX_SIZE_KB = 5120;

export const GALLERY_IMAGE_ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/bmp',
    'image/svg+xml',
    'image/webp',
];

type GalleryMode = 'create' | 'edit';

export function validateGalleryField(
    field: keyof GalleryFormValues,
    data: GalleryFormValues,
    t: Translate,
    mode: GalleryMode = 'create',
    hasExistingImage = false,
): string | undefined {
    const value = data[field];
    switch (field) {
        case 'label_en':
        case 'label_my':
        case 'label_zh': {
            if (typeof value !== 'string') break;

            const trimmed = value.trim();

            if (trimmed.length > GALLERY_LABEL_MAX_LENGTH) {
                return t(`cms.gallery.validation.${field}_max`);
            }

            break;
        }
        case 'image': {
            if (mode === 'edit' && hasExistingImage && !data.image) {
                return undefined;
            }

            if (!data.image) {
                return t('cms.gallery.validation.image_required');
            }

            const file = data.image;

            if (file.size > GALLERY_IMAGE_MAX_SIZE_KB * 1024) {
                return t('cms.gallery.validation.image_max_size');
            }

            if (!GALLERY_IMAGE_ACCEPTED_TYPES.includes(file.type)) {
                return t('cms.gallery.validation.image_invalid_type');
            }

            break;
        }
    }
    return undefined;
}

export function validateGallery(
    data: GalleryFormValues,
    t: Translate,
    mode: GalleryMode = 'create',
    hasExistingImage = false,
): Partial<Record<keyof GalleryFormValues, string>> {
    const errors: Partial<Record<keyof GalleryFormValues, string>> = {};

    const imageError = validateGalleryField('image', data, t, mode, hasExistingImage);

    if (imageError) {
        errors.image = imageError;
    }

    return errors;
}
