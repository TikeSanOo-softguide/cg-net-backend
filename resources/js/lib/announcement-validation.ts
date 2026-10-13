import type { AnnouncementFormValues } from '@/components/notification/announcement/AnnouncementForm';
import { dateTimeInputInTimezone } from '@/lib/utils';

type Translate = (key: string) => string;

type AnnouncementField = keyof AnnouncementFormValues;

export const ANNOUNCEMENT_TITLE_MAX_LENGTH = 120;
export const ANNOUNCEMENT_CONTENT_MAX_LENGTH = 5000;
export const ANNOUNCEMENT_TYPES = ['announce', 'system'] as const;

export type AnnouncementType = (typeof ANNOUNCEMENT_TYPES)[number];

export type AnnouncementValidationErrors = Partial<Record<AnnouncementField, string>>;

export function validateAnnouncementField(
    field: AnnouncementField,
    data: AnnouncementFormValues,
    t: Translate,
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'type': {
            if (!ANNOUNCEMENT_TYPES.includes(value as AnnouncementType)) {
                return t('notification.announcement.validation.type_required');
            }

            break;
        }

        case 'title_en':
        case 'title_zh':
        case 'title_my': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`notification.announcement.validation.${field}_required`);
            }
            if (trimmed.length > ANNOUNCEMENT_TITLE_MAX_LENGTH) {
                return t(`notification.announcement.validation.${field}_max`);
            }
            break;
        }

        case 'content_en':
        case 'content_my':
        case 'content_zh': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`notification.announcement.validation.${field}_required`);
            }
            if (trimmed.length > ANNOUNCEMENT_CONTENT_MAX_LENGTH) {
                return t(`notification.announcement.validation.${field}_max`);
            }
            break;
        }

        case 'start_date': {
            if (value === null || value === undefined || value === '') {
                break;
            }
            if (typeof value === 'string' && !isValidDateTime(value)) {
                return t('notification.announcement.validation.start_date');
            }
            break;
        }

        case 'end_date': {
            if (value === null || value === undefined || value === '') {
                break;
            }
            if (typeof value === 'string' && !isValidDateTime(value)) {
                return t('notification.announcement.validation.end_date');
            }
            if (
                typeof value === 'string' &&
                typeof data.start_date === 'string' &&
                data.start_date !== '' &&
                isValidDateTime(value) &&
                isValidDateTime(data.start_date) &&
                parseDateTime(value) < parseDateTime(data.start_date)
            ) {
                return t('notification.announcement.validation.end_date_after_or_equal');
            }
            break;
        }

        case 'is_active': {
            if (typeof value !== 'boolean') {
                return t('notification.announcement.validation.is_active_required');
            }

            break;
        }
    }

    return undefined;
}

export function validateAnnouncement(data: AnnouncementFormValues, t: Translate): AnnouncementValidationErrors {
    const errors: AnnouncementValidationErrors = {};

    (Object.keys(data) as AnnouncementField[]).forEach((field) => {
        const message = validateAnnouncementField(field, data, t);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

export function announcementSuccessMessage(field: AnnouncementField, t: Translate): string {
    return t(`notification.announcement.validation.${field}_ok`);
}

function isValidDateTime(value: string): boolean {
    return dateTimeInputInTimezone(value) !== null;
}

function parseDateTime(value: string): number {
    const normalized = dateTimeInputInTimezone(value);

    return normalized ? Date.parse(`${normalized}Z`) : Number.NaN;
}
