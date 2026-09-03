export type PhoneCountry = 'mm' | 'th' | 'cn' | 'unknown';

export type ParsedPhone = {
    country: PhoneCountry;
    dial: string;
    flagSrc: string;
    label: string;
    local: string;
    raw: string;
};

const COUNTRIES = [
    { country: 'mm' as const, dial: '959', label: 'Myanmar', flagSrc: '/images/flags/mm.svg' },
    { country: 'th' as const, dial: '66', label: 'Thailand', flagSrc: '/images/flags/th.svg' },
    { country: 'cn' as const, dial: '86', label: 'China', flagSrc: '/images/flags/cn.svg' },
] as const;

export const PHONE_COUNTRY_OPTIONS = COUNTRIES;

function digitsOnly(value: string): string {
    return value.replace(/\D+/g, '');
}

export function parsePhone(value: string | null | undefined): ParsedPhone {
    const raw = (value ?? '').trim();
    const digits = digitsOnly(raw);

    for (const option of COUNTRIES) {
        if (digits.startsWith(option.dial) && digits.length > option.dial.length) {
            return {
                country: option.country,
                dial: option.dial,
                flagSrc: option.flagSrc,
                label: option.label,
                local: digits.slice(option.dial.length),
                raw,
            };
        }
    }

    return {
        country: 'unknown',
        dial: '',
        flagSrc: '',
        label: '',
        local: digits || raw,
        raw,
    };
}

export function composePhone(country: PhoneCountry, local: string): string {
    const option = COUNTRIES.find((item) => item.country === country);
    const localDigits = digitsOnly(local);

    if (! option || localDigits === '') {
        return localDigits;
    }

    return `+${option.dial}${localDigits}`;
}

export function formatPhoneLocal(value: string | null | undefined): string {
    return parsePhone(value).local || '—';
}
