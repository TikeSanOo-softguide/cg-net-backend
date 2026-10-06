export type LanguageMessages = {
    en: string;
    my: string;
    zh: string;
};

export type ChatFlowActionOption = {
    value: string;
    label: string;
};

export type Option = {
    id: number;
    option_en: string;
    option_my: string;
    option_zh: string;
    action: string;
    next_step_id?: number | null;
    url?: string | null;
    reply_text_en?: string | null;
    reply_text_my?: string | null;
    reply_text_zh?: string | null;
    sort_order: number;
    is_active: boolean;
    next_step?: {
        id: number;
        name: string;
    } | null;
};

export type Step = {
    id: number;
    name: string;
    message_en: string;
    message_my: string;
    message_zh: string;
    is_start: boolean;
    sort_order: number;
    is_active: boolean;
    options: Option[];
};

export const emptyMessages: LanguageMessages = {
    en: '',
    my: '',
    zh: '',
};
