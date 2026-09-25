export type Person = {
    id: number;
    name?: string | null;
    username?: string | null;
};

export type ChatMessage = {
    id: number;
    sender_type: string;
    message_type: string;
    message: string | null;
    created_at: string;
};

export type ConversationSummary = {
    id: number;
    status: string;
    user: Person | null;
    agent: Person | null;
    latest_message: ChatMessage | null;
    unread_count: number;
    updated_at: string;
};

export type Conversation = ConversationSummary & {
    messages: ChatMessage[];
    current_step?: { name?: string | null } | null;
};

export type QuickReply = {
    id: number;
    keyword: string;
    response_en: string;
    response_my: string;
    response_zh: string;
};

export type ChatFilters = {
    search?: string;
    status?: string;
};
