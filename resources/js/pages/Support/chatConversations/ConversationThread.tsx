import { useForm } from '@inertiajs/react';
import { PaperclipIcon, SendIcon, SmileIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import { Avatar, formatDateTime } from './ConversationList';
import type { Conversation } from './types';

type ConversationThreadProps = { conversation: Conversation | null; insertedMessage?: string; canReply: boolean };

export function ConversationThread({ conversation, insertedMessage, canReply }: ConversationThreadProps) {
    const { t } = useTranslation();
    const form = useForm({ message: '' });
    const messagesContainerRef = useRef<HTMLDivElement | null>(null);
    const previousConversationIdRef = useRef<number | null>(null);
    const previousLastMessageIdRef = useRef<number | undefined>(undefined);
    const wasNearBottomRef = useRef(true);
    const pendingAgentSendRef = useRef(false);
    const [sendFailed, setSendFailed] = useState(false);
    const lastMessageId = conversation?.messages.at(-1)?.id;
    const messageCount = conversation?.messages.length ?? 0;

    useEffect(() => {
        if (insertedMessage) form.setData('message', insertedMessage);
    }, [insertedMessage]);

    useLayoutEffect(() => {
        const conversationId = conversation?.id ?? null;
        const switchedConversation = previousConversationIdRef.current !== conversationId;
        const receivedMessage = previousLastMessageIdRef.current !== lastMessageId;
        const container = messagesContainerRef.current;

        if (
            container &&
            (switchedConversation || (receivedMessage && (wasNearBottomRef.current || pendingAgentSendRef.current)))
        ) {
            container.scrollTop = container.scrollHeight;
            wasNearBottomRef.current = true;
        }

        previousConversationIdRef.current = conversationId;
        previousLastMessageIdRef.current = lastMessageId;

        if (receivedMessage) {
            pendingAgentSendRef.current = false;
        }
    }, [conversation?.id, lastMessageId, messageCount]);

    if (!conversation)
        return (
            <main className="flex min-h-0 items-center justify-center overflow-hidden p-6 text-sm text-muted-foreground">
                {t('support.chat_conversations.select_conversation')}
            </main>
        );

    const sendMessage = () => {
        if (!canReply || !form.data.message.trim()) return;

        pendingAgentSendRef.current = true;
        setSendFailed(false);
        form.post(`/support/conversations/${conversation.id}/messages`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => {
                pendingAgentSendRef.current = false;
                setSendFailed(true);
            },
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        sendMessage();
    };

    return (
        <main className="flex min-h-0 min-w-0 flex-col overflow-hidden bg-background">
            <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div className="flex min-w-0 items-center gap-3">
                    <Avatar name={conversation.user?.name ?? t('support.chat_conversations.guest')} />
                    <div className="min-w-0">
                        <h2 className="truncate text-sm font-semibold">
                            {conversation.user?.name ?? t('support.chat_conversations.guest')}
                        </h2>
                        <p className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            #{conversation.id} <span className="size-1.5 rounded-full bg-emerald-400" />{' '}
                            {conversation.agent?.username ?? t('support.chat_conversations.unassigned')}
                        </p>
                    </div>
                </div>
            </header>
            <div
                ref={messagesContainerRef}
                onScroll={(event) => {
                    const container = event.currentTarget;
                    wasNearBottomRef.current =
                        container.scrollHeight - container.scrollTop - container.clientHeight <= 80;
                }}
                className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6"
            >
                {conversation.messages.map((message) => {
                    const isAgent = message.sender_type === 'agent';
                    return (
                        <div
                            key={message.id}
                            className={cn('flex items-end gap-2', isAgent ? 'justify-end' : 'justify-start')}
                        >
                            {!isAgent ? (
                                <Avatar name={conversation.user?.name ?? t('support.chat_conversations.guest')} small />
                            ) : null}
                            <div className={cn('max-w-[82%] sm:max-w-[70%]', isAgent ? 'items-end' : 'items-start')}>
                                <div
                                    className={cn(
                                        'rounded-xl px-3.5 py-2.5 text-xs leading-relaxed',
                                        isAgent
                                            ? 'rounded-br-sm bg-primary text-primary-foreground'
                                            : 'rounded-bl-sm bg-muted text-foreground',
                                    )}
                                >
                                    {message.message || t('support.chat_conversations.attachment')}
                                </div>
                                <p className="mt-1 text-[10px] text-muted-foreground">
                                    {formatDateTime(message.created_at)}
                                </p>
                            </div>
                        </div>
                    );
                })}
            </div>
            {canReply ? (
                <form onSubmit={submit} className="border-t border-border p-3 sm:p-4">
                    <div className="flex items-end gap-2 rounded-lg border border-border bg-background p-2 focus-within:ring-2 focus-within:ring-ring/30">
                        <Button type="button" variant="ghost" size="icon" className="size-8 min-h-8">
                            <PaperclipIcon />
                        </Button>
                        <Input
                            value={form.data.message}
                            onChange={(event) => {
                                form.setData('message', event.target.value);
                                form.clearErrors('message');
                                setSendFailed(false);
                            }}
                            placeholder={t('support.chat_conversations.type_message')}
                            className="h-8 min-h-8 flex-1 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0"
                        />
                        <Button type="button" variant="ghost" size="icon" className="size-8 min-h-8">
                            <SmileIcon />
                        </Button>
                        <Button type="submit" size="sm" disabled={form.processing || !form.data.message.trim()}>
                            <SendIcon />{' '}
                            {form.processing
                                ? t('support.chat_conversations.sending')
                                : t('support.chat_conversations.send')}
                        </Button>
                    </div>
                    {sendFailed || form.errors.message ? (
                        <div className="mt-2 flex items-center justify-between gap-3 text-xs text-danger" role="alert">
                            <span>{t('support.chat_conversations.failed_to_send')}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={sendMessage}
                                disabled={form.processing}
                            >
                                {t('support.chat_conversations.retry')}
                            </Button>
                        </div>
                    ) : null}
                    <p className="mt-1 text-right text-[10px] text-muted-foreground">{form.data.message.length}/2000</p>
                </form>
            ) : null}
        </main>
    );
}
