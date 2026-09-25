import type { AppVersionFormData } from '@/components/settings/app-version/AppVersionForm';

type Translate = (key: string) => string;

export function validateAppVersionField(
    field: keyof AppVersionFormData,
    data: AppVersionFormData,
    t: Translate,
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'platform': {
            if (value === undefined || value === null || value === '') {
                return t('settings.app_version.validation.platform_required');
            }
            if (value !== 'android' && value !== 'ios') {
                return t('settings.app_version.validation.platform_required');
            }
            break;
        }

        case 'version': {
            if (typeof value !== 'string') return t('settings.app_version.validation.version_required');
            if (value.trim() === '') return t('settings.app_version.validation.version_required');
            break;
        }

        case 'minimum_version': {
            if (typeof value !== 'string') return t('settings.app_version.validation.minimum_version_required');
            if (value.trim() === '') return t('settings.app_version.validation.minimum_version_required');
            break;
        }

        case 'download_url': {
            if (typeof value !== 'string') return t('settings.app_version.validation.download_url_required');
            if (value.trim() === '') return t('settings.app_version.validation.download_url_required');
            try {
                const url = new URL(value);
                if (!url.protocol || !url.hostname) {
                    return t('settings.app_version.validation.download_url_url');
                }
            } catch {
                return t('settings.app_version.validation.download_url_url');
            }
            break;
        }

        case 'release_notes_en':
        case 'release_notes_zh':
        case 'release_notes_my': {
            if (typeof value !== 'string') {
                return t(`settings.app_version.validation.${field}_required`);
            }
            if (value.trim() === '') {
                return t(`settings.app_version.validation.${field}_required`);
            }
            break;
        }

        case 'force_update': {
            if (typeof value !== 'boolean') {
                return t('settings.app_version.validation.force_update_required');
            }
            break;
        }

        case 'status': {
            if (value !== 'active' && value !== 'inactive') {
                return t('settings.app_version.validation.status_required');
            }
            break;
        }

        default:
            break;
    }

    return undefined;
}

export function validateAppVersion(
    data: AppVersionFormData,
    t: Translate,
): Partial<Record<keyof AppVersionFormData, string>> {
    const errors: Partial<Record<keyof AppVersionFormData, string>> = {};

    (Object.keys(data) as (keyof AppVersionFormData)[]).forEach((field) => {
        if (field === 'id') {
            return;
        }

        const message = validateAppVersionField(field, data, t);
        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
