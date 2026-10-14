import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

export function appTimezone(): string {
    if (typeof document === 'undefined') {
        return 'UTC';
    }

    return document.querySelector<HTMLMetaElement>('meta[name="app-timezone"]')?.content || 'UTC';
}

export function dateInTimezone(date: Date, timeZone = appTimezone()): Date {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return new Date(
        Date.UTC(
            Number(values.year),
            Number(values.month) - 1,
            Number(values.day),
            Number(values.hour),
            Number(values.minute),
            Number(values.second),
        ),
    );
}

export function todayInTimezone(timeZone = appTimezone()): string {
    return dateInTimezone(new Date(), timeZone).toISOString().slice(0, 10);
}

export function dateTimeInputInTimezone(value: string, timeZone = appTimezone()): string | null {
    const trimmed = value.trim();
    const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/);

    if (match) {
        const [, year, month, day, hour, minute, second = '0'] = match;
        const date = new Date(
            Date.UTC(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute), Number(second)),
        );

        if (
            date.getUTCFullYear() !== Number(year) ||
            date.getUTCMonth() !== Number(month) - 1 ||
            date.getUTCDate() !== Number(day) ||
            date.getUTCHours() !== Number(hour) ||
            date.getUTCMinutes() !== Number(minute) ||
            date.getUTCSeconds() !== Number(second)
        ) {
            return null;
        }

        return `${trimmed.replace(' ', 'T').slice(0, 16)}:${second.padStart(2, '0')}`;
    }

    const date = new Date(trimmed);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const local = dateInTimezone(date, timeZone);
    const year = local.getUTCFullYear();
    const month = String(local.getUTCMonth() + 1).padStart(2, '0');
    const day = String(local.getUTCDate()).padStart(2, '0');
    const hour = String(local.getUTCHours()).padStart(2, '0');
    const minute = String(local.getUTCMinutes()).padStart(2, '0');
    const second = String(local.getUTCSeconds()).padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}:${second}`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
    const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(value);
    const isLocalDateTime = !isDateOnly && !hasTimezone && /^\d{4}-\d{2}-\d{2}[T ]/.test(value);
    const date = new Date(isDateOnly ? `${value}T00:00:00Z` : isLocalDateTime ? `${value.replace(' ', 'T')}Z` : value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: isDateOnly || isLocalDateTime ? 'UTC' : appTimezone(),
    }).format(date);
}

export function formatDateTime(
    value: string | null | undefined,
    isUTC: boolean | null = false,
    timeZone?: string,
): string {
    if (!value) {
        return '—';
    }

    const trimmed = value.trim();
    const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(trimmed);
    const date = new Date(
        !hasTimezone && /^\d{4}-\d{2}-\d{2}[T ]/.test(trimmed) ? `${trimmed.replace(' ', 'T')}Z` : trimmed,
    );

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const displayTimezone = hasTimezone ? (timeZone ?? (isUTC ? 'UTC' : appTimezone())) : 'UTC';
    const datePart = new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: displayTimezone,
    }).format(date);

    const timePart = new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
        timeZone: displayTimezone,
    }).format(date);

    return `${datePart} ${timePart}`;
}

export function formatTime(value: string | null | undefined, timeZone = appTimezone()): string {
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

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: isLocalDateTime ? 'UTC' : timeZone,
    }).format(date);
}

export function truncateText(value: string, maxLength: number): string {
    return value.length > maxLength ? `${value.slice(0, maxLength)}...` : value;
}
