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
): string | undefined {
    const value = data[field];

    if (field === 'image_url') {
        if (!value) {
            return undefined;
        }

        if (!(value instanceof File)) {
            return undefined;
        }
    }

    return undefined;
}

export function validatePackage(
    data: PackageFormValues,
    t: Translate,
): Partial<Record<keyof PackageFormValues, string>> {
    const errors: Partial<Record<keyof PackageFormValues, string>> = {};

    (Object.keys(data) as (keyof PackageFormValues)[]).forEach((field) => {
        const message = validatePackageField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
