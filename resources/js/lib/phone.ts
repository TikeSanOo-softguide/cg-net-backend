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
    { country: 'mm' as const, dial: '95', label: 'Myanmar', flagSrc: '/images/flags/mm.svg' },
    { country: 'th' as const, dial: '66', label: 'Thailand', flagSrc: '/images/flags/th.svg' },
    { country: 'cn' as const, dial: '86', label: 'China', flagSrc: '/images/flags/cn.svg' },
] as const;

export const PHONE_COUNTRY_OPTIONS = COUNTRIES;

function digitsOnly(value: string): string {
    return value.replace(/\D+/g, '');
}

export function parsePhone(value: string | null | undefined): ParsedPhone {
    const raw = (value ?? '').trim();
    const international = raw.startsWith('00') ? `+${raw.slice(2)}` : raw;
    const digits = digitsOnly(international);

    for (const option of COUNTRIES) {
        if (digits.startsWith(option.dial) && digits.length > option.dial.length) {
            const local = digits.slice(option.dial.length);

            return {
                country: option.country,
                dial: option.dial,
                flagSrc: option.flagSrc,
                label: option.label,
                local: option.country === 'mm' || option.country === 'th' ? local.replace(/^0/, '') : local,
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
    let localDigits = digitsOnly(local);

    if (country === 'mm' || country === 'th') {
        localDigits = localDigits.replace(/^0/, '');
    }

    if (!option || localDigits === '') {
        return localDigits;
    }

    return `${option.dial}${localDigits}`;
}

export function formatPhoneInternational(value: string | null | undefined): string {
    const normalized = value ? (normalizePhone(value) ?? value) : value;
    const parsed = parsePhone(normalized);

    return parsed.country === 'unknown' ? parsed.raw || '—' : `+${parsed.dial}${parsed.local}`;
}

const LOCAL_PATTERN: Record<Exclude<PhoneCountry, 'unknown'>, RegExp> = {
    mm: /^9[2-9]\d{7,10}$/,
    th: /^(?:14\d{7}|[689]\d{8})$/,
    cn: /^1[3-9]\d{9}$/,
};

function normalizePhone(value: string): string | null {
    let phone = value.trim().replace(/[\s().-]+/g, '');

    if (phone.startsWith('00')) {
        phone = `+${phone.slice(2)}`;
    }

    if (phone.startsWith('09')) {
        phone = `+95${phone.slice(1)}`;
    } else if (phone.startsWith('06') || phone.startsWith('08')) {
        phone = `+66${phone.slice(1)}`;
    }

    phone = phone.replace(/^\++/, '');

    if (phone.startsWith('950')) {
        phone = `95${phone.slice(3)}`;
    } else if (phone.startsWith('660')) {
        phone = `66${phone.slice(3)}`;
    }

    return /^[1-9][0-9]{7,14}$/.test(phone) ? phone : null;
}

export function isValidAppUserPhone(value: string | null | undefined): boolean {
    const normalized = normalizePhone(value ?? '');
    const parsed = parsePhone(normalized);

    if (normalized === null || parsed.country === 'unknown' || parsed.local === '') {
        return false;
    }

    return LOCAL_PATTERN[parsed.country].test(parsed.local);
}
