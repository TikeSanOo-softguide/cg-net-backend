import type { QuickReplyFormValues } from '@/components/support/quick-reply/QuickReplyForm';

type Translate = (key: string) => string;

export const QUICK_REPLY_KEYWORD_MAX_LENGTH = 50;
export const QUICK_REPLY_RESPONSE_MAX_LENGTH = 5000;

export function validateQuickReplyField(
    field: keyof QuickReplyFormValues,
    data: QuickReplyFormValues,
    categories: string[],
    t: Translate,
    existingKeywords: string[] = [],
): string | undefined {
    const value = data[field];

    switch (field) {
        case 'keyword': {
            if (typeof value !== 'string' || value.trim() === '') {
                return t('support.quick_replies.validation.keyword_required');
            }

            if (value.trim().length > QUICK_REPLY_KEYWORD_MAX_LENGTH) {
                return t('support.quick_replies.validation.keyword_max');
            }

            if (existingKeywords.some((keyword) => keyword.trim() === value.trim())) {
                return t('support.quick_replies.validation.keyword_unique');
            }

            break;
        }

        case 'category': {
            if (typeof value !== 'string' || !categories.includes(value)) {
                return t('support.quick_replies.validation.category_invalid');
            }

            break;
        }

        case 'response_en':
        case 'response_my':
        case 'response_zh': {
            if (typeof value !== 'string' || value.trim() === '') {
                return t(`support.quick_replies.validation.${field}_required`);
            }

            if (value.trim().length > QUICK_REPLY_RESPONSE_MAX_LENGTH) {
                return t(`support.quick_replies.validation.${field}_max`);
            }

            break;
        }
    }

    return undefined;
}

export function validateQuickReply(
    data: QuickReplyFormValues,
    categories: string[],
    t: Translate,
    existingKeywords: string[] = [],
): Partial<Record<keyof QuickReplyFormValues, string>> {
    const errors: Partial<Record<keyof QuickReplyFormValues, string>> = {};

    (Object.keys(data) as (keyof QuickReplyFormValues)[]).forEach((field) => {
        const message = validateQuickReplyField(field, data, categories, t, existingKeywords);

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}
