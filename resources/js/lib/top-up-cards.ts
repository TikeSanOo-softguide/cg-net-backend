import { appTimezone, dateInTimezone } from '@/lib/utils';

export const TOP_UP_CARD_CURRENCY = 'Points';

export type TopUpCardRow = {
    id: number;
    serial_no: string;
    pin?: string;
    amount: number;
    status: string;
    expires_at: string | null;
    redeemed_at: string | null;
    redeemed_by_id?: number | null;
    redeemed_by: string | null;
    redeemed_by_phone?: string | null;
    office_id?: number | null;
    office?: { name: string; code: string } | null;
    batch_no: string | null;
    batch_status: string | null;
    transaction_id: string | number;
    transaction_no: string | null;
};

export type TopUpCardFilters = {
    search: string;
    status: string;
    amount: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export type CardHistoryFilters = {
    search: string;
    status: string;
    amount: string;
    office: string;
    batch: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export type RedeemHistoryFilters = {
    search: string;
    amount: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export type RedeemHistoryStats = {
    total: number;
    value: string;
    month: number;
    customers: number;
};

export type BatchRow = {
    id: number;
    batch_no: string;
    total_value: number;
    status: string;
    quantity: number;
    expires_at: string | null;
};

export type BatchFilters = {
    search: string;
    status: string;
    from: string;
    to: string;
    sort: string;
    direction: 'asc' | 'desc';
};

export function formatTopUpNumber(value: string | number): string {
    return Number(value).toLocaleString();
}

export function formatTopUpAmount(value: string | number): string {
    return `${formatTopUpNumber(value)} ${TOP_UP_CARD_CURRENCY}`;
}

export function formatTopUpPin(value: string | null | undefined): string {
    const digits = (value ?? '').replace(/\D/g, '');

    if (digits.length === 0) {
        return '•••• - •••• - •••• - ••••';
    }

    return digits.match(/.{1,4}/g)?.join(' - ') ?? digits;
}

export function topUpCardQrValue(card: Pick<TopUpCardRow, 'serial_no' | 'pin'>): string {
    if (card.pin) {
        return JSON.stringify({ serial: card.serial_no, pin: card.pin });
    }

    return card.serial_no;
}

export function expiryDateIn(days: number): string {
    const date = dateInTimezone(new Date());
    date.setUTCDate(date.getUTCDate() + days);

    return calendarDate(date);
}

export function expiryDateInYears(years: number): string {
    const date = dateInTimezone(new Date());
    date.setUTCFullYear(date.getUTCFullYear() + years);

    return calendarDate(date);
}

export function defaultExpiryDate(): string {
    return expiryDateInYears(2);
}

export function isoDate(date = new Date()): string {
    return calendarDate(dateInTimezone(date));
}

export function dateDaysAgo(days: number): string {
    const date = dateInTimezone(new Date());
    date.setUTCDate(date.getUTCDate() - days);

    return calendarDate(date);
}

export function startOfMonthDate(): string {
    const date = dateInTimezone(new Date());
    date.setUTCDate(1);

    return calendarDate(date);
}

export function formatTopUpDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const trimmed = value.trim();
    const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(trimmed);
    const isLocalDateTime = !hasTimezone && /^\d{4}-\d{2}-\d{2}[T ]/.test(trimmed);
    const date = new Date(isLocalDateTime ? `${trimmed.replace(' ', 'T')}Z` : trimmed);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: isLocalDateTime ? 'UTC' : appTimezone(),
    });
}

function calendarDate(date: Date): string {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
