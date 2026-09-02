import type { AnnouncementFormValues } from '@/components/notification/announcement/AnnouncementForm';

type Translate = (key: string) => string;

type AnnouncementField = keyof AnnouncementFormValues;

export type AnnouncementValidationErrors = Partial<Record<AnnouncementField, string>>;

export function validateAnnouncementField(
    field: AnnouncementField,
    data: AnnouncementFormValues,
    t: Translate,
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'content_en':
        case 'content_my':
        case 'content_zh': {
            if (typeof value !== 'string') break;
            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`notification.announcement.validation.${field}_required`);
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
                parseDateTime(value).getTime() < parseDateTime(data.start_date).getTime()
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

function parseDateTime(value: string): Date {
    if (value.includes('T')) {
        return new Date(value);
    }

    return new Date(`${value}:00`);
}

function isValidDateTime(value: string): boolean {
    if (!value) {
        return false;
    }

    const normalized = value.trim();
    const date = normalized.includes('T') ? new Date(normalized) : new Date(`${normalized}:00`);

    return !Number.isNaN(date.getTime());
}
