type Translate = (key: string) => string;

export type PushNotificationFormValues = {
    title_en: string;
    title_zh: string;
    title_my: string;
    schedule_date: string;
    schedule_time: string;
};

export const PUSH_TITLE_MAX_LENGTH = 120;

export type PushNotificationField = keyof PushNotificationFormValues;

export type PushNotificationValidationErrors = Partial<Record<PushNotificationField, string>>;

function parseScheduleDateTime(date: string, time: string): Date | null {
    if (!date || !time) {
        return null;
    }

    const match = time.match(/^(\d{2}):(\d{2})$/);
    if (!match) {
        return null;
    }

    const [year, month, day] = date.split('-').map(Number);
    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    const parsed = new Date(year, month - 1, day, hours, minutes, 0, 0);

    if (
        Number.isNaN(parsed.getTime()) ||
        parsed.getFullYear() !== year ||
        parsed.getMonth() !== month - 1 ||
        parsed.getDate() !== day
    ) {
        return null;
    }

    return parsed;
}

export function validatePushNotificationField(
    field: PushNotificationField,
    data: PushNotificationFormValues,
    t: Translate,
    mode: 'now' | 'schedule' = 'now',
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'title_en':
        case 'title_zh':
        case 'title_my': {
            const trimmed = value.trim();
            if (trimmed === '') {
                return t(`notification.push.validation.${field}_required`);
            }
            if (trimmed.length > PUSH_TITLE_MAX_LENGTH) {
                return t(`notification.push.validation.${field}_max`);
            }
            break;
        }

        case 'schedule_date': {
            if (mode !== 'schedule') {
                break;
            }
            if (!value.trim()) {
                return t('notification.push.validation.schedule_date_required');
            }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                return t('notification.push.validation.schedule_date_invalid');
            }
            break;
        }

        case 'schedule_time': {
            if (mode !== 'schedule') {
                break;
            }
            if (!value.trim()) {
                return t('notification.push.validation.schedule_time_required');
            }
            if (!/^\d{2}:\d{2}$/.test(value)) {
                return t('notification.push.validation.schedule_time_invalid');
            }

            const scheduled = parseScheduleDateTime(data.schedule_date, value);
            if (!scheduled) {
                return t('notification.push.validation.schedule_time_invalid');
            }
            if (scheduled.getTime() <= Date.now()) {
                return t('notification.push.validation.scheduled_at_future');
            }
            break;
        }
    }

    return undefined;
}

export function validatePushNotification(
    data: PushNotificationFormValues,
    t: Translate,
    mode: 'now' | 'schedule',
): PushNotificationValidationErrors {
    const fields: PushNotificationField[] =
        mode === 'schedule'
            ? ['title_en', 'title_zh', 'title_my', 'schedule_date', 'schedule_time']
            : ['title_en', 'title_zh', 'title_my'];

    const errors: PushNotificationValidationErrors = {};

    fields.forEach((field) => {
        const message = validatePushNotificationField(field, data, t, mode);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
