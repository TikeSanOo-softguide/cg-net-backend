import type { RegionFormValues } from '@/components/region/RegionForm';

type TranslationFunction = (key: string) => string;

export type RegionField = keyof RegionFormValues;

export type ExistingRegion = {
    id: number;
    name_en: string;
    name_my: string;
    name_zh: string;
    state_id?: number | null;
    region_id?: number | null;
};

export const validateRegionField = (
    field: RegionField,
    data: RegionFormValues,
    t: TranslationFunction,
    type: 'state' | 'region' | 'area',
    existingRecords: ExistingRegion[] = [],
    editingId?: number,
): string | undefined => {
    const value = data[field];

    const hasMoreThan7Decimals = (value: string | number | null | undefined): boolean => {
        if (value === null || value === undefined || value === '') {
            return false;
        }

        const valueString = String(value).trim();
        const decimalPart = valueString.split('.')[1];

        return decimalPart ? decimalPart.length > 7 : false;
    };

    const isDuplicateName = (nameField: 'name_en' | 'name_my' | 'name_zh'): boolean => {
        const currentValue = String(data[nameField] ?? '')
            .trim()
            .toLowerCase();

        if (!currentValue) {
            return false;
        }

        return existingRecords.some((item) => {
            if (editingId !== undefined && item.id === editingId) {
                return false;
            }

            const existingValue = String(item[nameField] ?? '')
                .trim()
                .toLowerCase();

            if (existingValue !== currentValue) {
                return false;
            }

            if (type === 'state') {
                return true;
            }

            if (type === 'region') {
                return data.state_id !== null && data.state_id !== undefined && item.state_id === Number(data.state_id);
            }

            if (type === 'area') {
                return (
                    data.region_id !== null && data.region_id !== undefined && item.region_id === Number(data.region_id)
                );
            }

            return false;
        });
    };

    switch (field) {
        case 'name_en':
        case 'name_my':
        case 'name_zh': {
            if (typeof value !== 'string') {
                break;
            }

            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`regions.validation.${field}_required`);
            }

            if (trimmed.length > 50) {
                return t(`regions.validation.${field}_max`);
            }

            if (isDuplicateName(field)) {
                return t(`regions.validation.${field}_unique`);
            }

            break;
        }

        case 'state_id':
            if (type !== 'state' && !data.state_id) {
                return t('regions.validation.state_required');
            }

            break;

        case 'region_id':
            if (type === 'area' && !data.region_id) {
                return t('regions.validation.region_required');
            }

            break;
        case 'latitude':
        case 'longitude': {
            const coordinate = field === 'latitude' ? data.latitude : data.longitude;
            if (coordinate === null || coordinate === undefined || String(coordinate).trim() === '') {
                return t(`regions.validation.${field}_required`);
            }
            const min = field === 'latitude' ? -90 : -180;
            const max = field === 'latitude' ? 90 : 180;
            if (Number(coordinate) < min || Number(coordinate) > max) {
                return t(`regions.validation.${field}_between`);
            }
            if (hasMoreThan7Decimals(coordinate)) {
                return t(`regions.validation.${field}_decimal`);
            }
            break;
        }
    }

    return undefined;
};

export const validateRegion = (
    data: RegionFormValues,
    t: TranslationFunction,
    type: 'state' | 'region' | 'area',
    existingRecords: ExistingRegion[] = [],
    editingId?: number,
): Partial<Record<RegionField, string>> => {
    const errors: Partial<Record<RegionField, string>> = {};

    const fields: RegionField[] = ['name_en', 'name_my', 'name_zh', 'latitude', 'longitude', 'state_id', 'region_id'];

    fields.forEach((field) => {
        const error = validateRegionField(field, data, t, type, existingRecords, editingId);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
};
