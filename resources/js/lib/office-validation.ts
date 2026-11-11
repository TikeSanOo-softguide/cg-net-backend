export type OfficeFormValues = {
    name_en: string;
    name_mm: string;
    name_zh: string;
    cd: string;
    address_en: string;
    address_mm: string;
    address_zh: string;
    phone_num1: string;
    phone_num2: string;
    phone_num3: string;
    telegram_link: string;
    wechat_link: string;
    viber_link: string;
};

type OfficeField = keyof OfficeFormValues;
type Translate = (key: string) => string;

const fields: OfficeField[] = [
    'name_en',
    'name_mm',
    'name_zh',
    'cd',
    'address_en',
    'address_mm',
    'address_zh',
    'phone_num1',
    'phone_num2',
    'phone_num3',
    'telegram_link',
    'wechat_link',
    'viber_link',
];

export function validateOfficeCdUnique(
    cd: OfficeFormValues['cd'],
    officeCds: string[],
    currentCd: string | undefined,
    t: Translate,
): string | undefined {
    if (cd !== '' && officeCds.includes(cd) && cd !== currentCd) {
        return t('top_up_cards.validation.office_cd_unique');
    }

    return undefined;
}

export function validateOfficeField(field: OfficeField, data: OfficeFormValues, t: Translate): string | undefined {
    const value = data[field].trim();

    if (field === 'cd') {
        if (value === '') {
            return t('top_up_cards.validation.office_cd_required');
        }
        if (!/^\d{2}$/.test(value)) {
            return t('top_up_cards.validation.office_cd_invalid');
        }
        return undefined;
    }

    const isName = field.startsWith('name_');
    const isAddress = field.startsWith('address_');
    const isRequired = isName || isAddress || field === 'phone_num1';

    if (isRequired && value === '') {
        return t(`top_up_cards.validation.office_${isName ? 'name' : isAddress ? 'address' : 'phone'}_required`);
    }
    if (isName && value.length > 50) {
        return t('top_up_cards.validation.office_name_max');
    }
    if (isAddress && value.length > 255) {
        return t('top_up_cards.validation.office_address_max');
    }
    if (field.startsWith('phone_num') && value.length > 16) {
        return t('top_up_cards.validation.office_phone_max');
    }
    if (field.endsWith('_link') && value.length > 255) {
        return t('top_up_cards.validation.office_link_max');
    }

    return undefined;
}

export function validateOffice(data: OfficeFormValues, t: Translate): Partial<Record<OfficeField, string>> {
    const errors: Partial<Record<OfficeField, string>> = {};

    fields.forEach((field) => {
        const error = validateOfficeField(field, data, t);

        if (error) {
            errors[field] = error;
        }
    });

    return errors;
}
