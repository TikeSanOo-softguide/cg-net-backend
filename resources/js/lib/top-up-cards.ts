export const TOP_UP_CARD_PRESETS = [50, 100, 250, 500] as const;

export const TOP_UP_CARD_CURRENCY = 'Points';

export type TopUpCardRow = {
    id: number;
    serial_no: string;
    pin?: string;
    amount: string | number;
    status: string;
    expires_at: string | null;
    redeemed_at: string | null;
    redeemed_by_id?: number | null;
    redeemed_by: string | null;
    redeemed_by_phone?: string | null;
    batch_no: string | null;
    batch_status: string | null;
    transaction_id: string | number;
    transaction_type: string | null;
    transaction_status: string | null;
    transaction_amount: number | null;
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
    const date = new Date();
    date.setDate(date.getDate() + days);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function expiryDateInYears(years: number): string {
    const date = new Date();
    date.setFullYear(date.getFullYear() + years);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function defaultExpiryDate(): string {
    return expiryDateInYears(2);
}

export function isoDate(date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function dateDaysAgo(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() - days);

    return isoDate(date);
}

export function startOfMonthDate(): string {
    const date = new Date();
    date.setDate(1);

    return isoDate(date);
}

export function formatTopUpDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
