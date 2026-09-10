import type { PackageFormValues } from '@/components/package/PackageForm';

type Translate = (key: string, options?: Record<string, string | number>) => string;

export const PACKAGE_IMAGE_WIDTH = 260;
export const PACKAGE_IMAGE_HEIGHT = 348;
export const PACKAGE_IMAGE_MAX_SIZE_KB = 5120;
export const PACKAGE_IMAGE_ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function toDimensionMessage(t: Translate): string {
    const fallback = `Image must be ${PACKAGE_IMAGE_WIDTH} x ${PACKAGE_IMAGE_HEIGHT} pixels.`;
    const translated = t('packages.validation.image_size');

    return translated && translated !== 'packages.validation.image_size' ? translated : fallback;
}

function readImageDimensions(file: File): Promise<{ width: number; height: number }> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Failed to read image dimensions.'));
        };

        image.src = url;
    });
}

export async function validatePackageImageFile(file: File, t: Translate): Promise<string | undefined> {
    if (!PACKAGE_IMAGE_ACCEPTED_TYPES.includes(file.type)) {
        return t('packages.validation.image_invalid_type') || 'Image must be a valid image file.';
    }

    if (file.size > PACKAGE_IMAGE_MAX_SIZE_KB * 1024) {
        return t('packages.validation.image_max_size') || 'Image size must not exceed 5 MB.';
    }

    try {
        const { width, height } = await readImageDimensions(file);

        if (width !== PACKAGE_IMAGE_WIDTH || height !== PACKAGE_IMAGE_HEIGHT) {
            return toDimensionMessage(t);
        }
    } catch {
        return t('packages.validation.image_invalid_type') || 'Image must be a valid image file.';
    }

    return undefined;
}

export function validatePackageField(
    field: keyof PackageFormValues,
    data: PackageFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
    hasExistingImage = false,
): string | undefined {
    const value = data[field];

    if (field === 'network_id') {
        if (value === '' || value === null || value === undefined) {
            return t('packages.validation.network_required');
        }

        return undefined;
    }

    if (field === 'speed_id') {
        if (value === '' || value === null || value === undefined) {
            return t('packages.validation.speed_required');
        }

        return undefined;
    }

    if (field === 'term_id') {
        if (value === '' || value === null || value === undefined) {
            return t('packages.validation.term_required');
        }

        return undefined;
    }

    if (field === 'price') {
        if (value === '') {
            return t('packages.validation.price_required');
        }

        const numericValue = Number(value);

        if (Number.isNaN(numericValue)) {
            return t('packages.validation.price_numeric');
        }

        if (numericValue < 0) {
            return t('packages.validation.price_min');
        }

        return undefined;
    }

    if (field === 'installation_fee') {
        if (value === '') {
            return t('packages.validation.installation_fee_required');
        }

        const numericValue = Number(value);

        if (Number.isNaN(numericValue)) {
            return t('packages.validation.price_numeric');
        }

        if (numericValue < 0) {
            return t('packages.validation.price_min');
        }

        return undefined;
    }

    if (field === 'sort_order') {
        if (value === '' || value === null || value === undefined) {
            return t('packages.validation.sort_order_required');
        }

        if (!Number.isInteger(Number(value))) {
            return t('packages.validation.sort_order_required');
        }

        return undefined;
    }

    return undefined;
}

export function validatePackage(
    data: PackageFormValues,
    t: Translate,
    mode: 'create' | 'edit' = 'create',
    hasExistingImage = false,
): Partial<Record<keyof PackageFormValues, string>> {
    const errors: Partial<Record<keyof PackageFormValues, string>> = {};

    (Object.keys(data) as (keyof PackageFormValues)[]).forEach((field) => {
        const message = validatePackageField(field, data, t, mode, hasExistingImage);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function packageSuccessMessage(field: keyof PackageFormValues, t: Translate): string {
    switch (field) {
        case 'network_id':
            return t('common.valid');
        case 'speed_id':
            return t('common.valid');
        case 'term_id':
            return t('common.valid');
        case 'price':
            return t('common.valid');
        case 'installation_fee':
            return t('common.valid');
        case 'sort_order':
            return t('common.valid');
        case 'image_url':
            return t('common.valid');
        default:
            return '';
    }
}
