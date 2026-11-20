type Translate = (key: string) => string;

export type GeneralContentField =
    'title_en' | 'title_zh' | 'title_my' | 'description_en' | 'description_zh' | 'description_my';

export type GeneralContentValues = Record<GeneralContentField, string>;
export type GeneralContentValidationErrors = Partial<Record<GeneralContentField, string>>;

export const GENERAL_CONTENT_TITLE_MAX_LENGTH = 120;
export const GENERAL_CONTENT_DESCRIPTION_MAX_LENGTH = 5000;

export function validateGeneralContentField(
    field: GeneralContentField,
    value: string,
    t: Translate,
): string | undefined {
    if (value.trim() === '') {
        return t(`settings.general_settings.validation.${field}_required`);
    }

    if (field.startsWith('title_') && value.length > GENERAL_CONTENT_TITLE_MAX_LENGTH) {
        return t(`settings.general_settings.validation.${field}_max`);
    }

    if (field.startsWith('description_') && value.length > GENERAL_CONTENT_DESCRIPTION_MAX_LENGTH) {
        return t(`settings.general_settings.validation.${field}_max`);
    }

    return undefined;
}

export function validateGeneralContent(values: GeneralContentValues, t: Translate): GeneralContentValidationErrors {
    const errors: GeneralContentValidationErrors = {};

    (Object.keys(values) as GeneralContentField[]).forEach((field) => {
        const error = validateGeneralContentField(field, values[field], t);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
}

export function getGeneralContentServerError(
    error: string | undefined,
    field: GeneralContentField,
    t: Translate,
): string | undefined {
    if (!error) {
        return undefined;
    }

    const message = error.toLowerCase();

    if (message.includes('required')) {
        return t(`settings.general_settings.validation.${field}_required`);
    }

    if (
        message.includes('may not be greater than') ||
        message.includes('must not be greater than') ||
        message.includes('maximum')
    ) {
        return t(`settings.general_settings.validation.${field}_max`);
    }

    return error;
}
