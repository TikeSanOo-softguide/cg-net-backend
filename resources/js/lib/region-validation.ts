import type { RegionFormValues } from '@/components/region/RegionForm';

type TranslationFunction = (key: string) => string;

export type RegionField = keyof RegionFormValues;

export const validateRegionField = (
    field: RegionField,
    data: RegionFormValues,
    t: TranslationFunction,
    type: 'state' | 'region' | 'area',
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
    switch (field) {
        case 'name_en':
        case 'name_my':
        case 'name_zh':
            if (typeof value !== 'string') break;

            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`regions.validation.${field}_required`);
            }

            if (trimmed.length > 255) {
                return t(`regions.validation.${field}_max`);
            }

            break;

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
            if (
                data.latitude !== null &&
                data.latitude !== '' &&
                (Number(data.latitude) < -90 || Number(data.latitude) > 90)
            ) {
                return t('regions.validation.latitude_between');
            }

            if (hasMoreThan7Decimals(data.latitude)) {
                return t('regions.validation.latitude_decimal');
            }

            break;

        case 'longitude':
            if (
                data.longitude !== null &&
                data.longitude !== '' &&
                (Number(data.longitude) < -180 || Number(data.longitude) > 180)
            ) {
                return t('regions.validation.longitude_between');
            }

            if (hasMoreThan7Decimals(data.longitude)) {
                return t('regions.validation.longitude_decimal');
            }
            break;
    }

    return undefined;
};

export const validateRegion = (
    data: RegionFormValues,
    t: TranslationFunction,
    type: 'state' | 'region' | 'area',
): Partial<Record<RegionField, string>> => {
    const errors: Partial<Record<RegionField, string>> = {};

    const fields: RegionField[] = ['name_en', 'name_my', 'name_zh', 'latitude', 'longitude', 'state_id', 'region_id'];

    fields.forEach((field) => {
        const error = validateRegionField(field, data, t, type);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
};
