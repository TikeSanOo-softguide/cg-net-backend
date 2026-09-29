import type {
    ChatFlowActionOption,
    LanguageMessages,
    Option,
} from '@/components/support/chat-flow-step/ChatFlowStepTypes';

type Translate = (key: string) => string;

export const CHAT_FLOW_STEP_NAME_MAX_LENGTH = 50;
export const CHAT_FLOW_MESSAGE_MAX_LENGTH = 5000;
export const CHAT_FLOW_OPTION_LABEL_MAX_LENGTH = 50;
export const CHAT_FLOW_REPLY_MAX_LENGTH = 5000;
export const CHAT_FLOW_URL_MAX_LENGTH = 2048;

type LanguageField = keyof LanguageMessages;
type LanguageErrors = Partial<Record<LanguageField, string>>;

export type StepFormData = {
    name: string;
    messages: LanguageMessages;
};

export type OptionFormData = {
    labels: LanguageMessages;
    action: string;
    nextStepId: string;
    url: string;
    replies: LanguageMessages;
    sortOrder: string;
    isActive: boolean;
};

function validateMessages(
    messages: LanguageMessages,
    t: Translate,
    field: 'message' | 'option_label' | 'reply',
    maxLength: number,
): LanguageErrors {
    const errors: LanguageErrors = {};

    (Object.keys(messages) as LanguageField[]).forEach((language) => {
        const value = messages[language].trim();

        if (!value) {
            errors[language] = t(`support.chatbot_flows.validation.${field}_${language}_required`);
        } else if (value.length > maxLength) {
            errors[language] = t(`support.chatbot_flows.validation.${field}_${language}_max`);
        }
    });

    return errors;
}

export function validateStepForm(
    data: StepFormData,
    t: Translate,
): {
    name?: string;
    messages: LanguageErrors;
} {
    const errors: { name?: string; messages: LanguageErrors } = {
        messages: validateMessages(data.messages, t, 'message', CHAT_FLOW_MESSAGE_MAX_LENGTH),
    };

    const name = data.name.trim();

    if (!name) {
        errors.name = t('support.chatbot_flows.validation.step_name_required');
    } else if (name.length > CHAT_FLOW_STEP_NAME_MAX_LENGTH) {
        errors.name = t('support.chatbot_flows.validation.step_name_max');
    }

    return errors;
}

export function validateOptionForm(
    data: OptionFormData,
    actionOptions: ChatFlowActionOption[],
    currentStepId: number,
    t: Translate,
    existingOptions: Option[] = [],
    editingOptionId?: number,
): {
    labels: LanguageErrors;
    action?: string;
    nextStepId?: string;
    url?: string;
    replies: LanguageErrors;
    sortOrder?: string;
    isActive?: string;
} {
    const errors: {
        labels: LanguageErrors;
        action?: string;
        nextStepId?: string;
        url?: string;
        replies: LanguageErrors;
        sortOrder?: string;
        isActive?: string;
    } = {
        labels: validateMessages(data.labels, t, 'option_label', CHAT_FLOW_OPTION_LABEL_MAX_LENGTH),
        replies: {},
    };

    if (!actionOptions.some((option) => option.value === data.action)) {
        errors.action = t('support.chatbot_flows.validation.action_required');
    }

    const order = data.sortOrder.trim();
    const numericOrder = Number(order);

    if (!order) {
        errors.sortOrder = t('support.chatbot_flows.validation.sort_order_required');
    } else if (!/^\d+$/.test(order) || !Number.isSafeInteger(numericOrder) || numericOrder > 99) {
        errors.sortOrder = t('support.chatbot_flows.validation.sort_order_invalid');
    } else if (existingOptions.some((option) => option.id !== editingOptionId && option.sort_order === numericOrder)) {
        errors.sortOrder = t('support.chatbot_flows.validation.sort_order_duplicate');
    }

    if (typeof data.isActive !== 'boolean') {
        errors.isActive = t('support.chatbot_flows.validation.is_active_required');
    }

    if (data.action === 'go_to_step') {
        if (!data.nextStepId) {
            errors.nextStepId = t('support.chatbot_flows.validation.next_step_required');
        } else if (Number(data.nextStepId) === currentStepId) {
            errors.nextStepId = t('support.chatbot_flows.validation.next_step_self');
        }
    }

    if (data.action === 'go_to_url') {
        const url = data.url.trim();

        if (!url) {
            errors.url = t('support.chatbot_flows.validation.url_required');
        } else if (url.length > CHAT_FLOW_URL_MAX_LENGTH) {
            errors.url = t('support.chatbot_flows.validation.url_max');
        } else {
            try {
                const parsedUrl = new URL(url);

                if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
                    errors.url = t('support.chatbot_flows.validation.url_invalid');
                }
            } catch {
                errors.url = t('support.chatbot_flows.validation.url_invalid');
            }
        }
    }

    if (data.action === 'reply_text') {
        errors.replies = validateMessages(data.replies, t, 'reply', CHAT_FLOW_REPLY_MAX_LENGTH);
    }

    return errors;
}

export function hasValidationErrors(errors: object): boolean {
    return Object.values(errors).some((error) => {
        if (typeof error === 'string') {
            return Boolean(error);
        }

        return error != null && Object.keys(error).length > 0;
    });
}
