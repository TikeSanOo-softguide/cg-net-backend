export type OfficeFormValues = {
    name: string;
    cd: number | '';
    address: string;
};

type Translate = (key: string) => string;

export function validateOfficeCdUnique(
    cd: OfficeFormValues['cd'],
    officeCds: number[],
    currentCd: number | undefined,
    t: Translate,
): string | undefined {
    if (cd !== '' && officeCds.includes(cd) && cd !== currentCd) {
        return t('top_up_cards.validation.office_cd_unique');
    }

    return undefined;
}

export function validateOfficeField(
    field: keyof OfficeFormValues,
    data: OfficeFormValues,
    t: Translate,
): string | undefined {
    const value = field === 'cd' ? String(data.cd) : String(data[field]).trim();

    if (value === '') {
        return t(`top_up_cards.validation.office_${field}_required`);
    }

    if (field === 'name' && value.length > 50) {
        return t('top_up_cards.validation.office_name_max');
    }

    if (field === 'cd' && (!/^\d{2}$/.test(value) || Number(value) > 99)) {
        return t('top_up_cards.validation.office_cd_invalid');
    }

    if (field === 'address' && value.length > 255) {
        return t('top_up_cards.validation.office_address_max');
    }

    return undefined;
}

export function validateOffice(data: OfficeFormValues, t: Translate): Partial<Record<keyof OfficeFormValues, string>> {
    const errors: Partial<Record<keyof OfficeFormValues, string>> = {};

    (Object.keys(data) as (keyof OfficeFormValues)[]).forEach((field) => {
        const error = validateOfficeField(field, data, t);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
}