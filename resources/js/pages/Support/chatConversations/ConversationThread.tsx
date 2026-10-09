import { useForm } from '@inertiajs/react';
import { LockKeyholeIcon, PaperclipIcon, SendIcon, SmileIcon, UserCheckIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

import { Avatar, formatDateTime } from './ConversationList';
import type { Conversation, QuickReply } from './types';

type ReplyLanguage = 'en' | 'my' | 'zh';

type ConversationThreadProps = {
    conversation: Conversation | null;
    insertedMessage?: string;
    canReply: boolean;
    canManage: boolean;
    quickReplies: QuickReply[];
};

function localizedReply(reply: QuickReply, language: ReplyLanguage): string {
    if (language === 'my') return reply.response_my;
    if (language === 'zh') return reply.response_zh;

    return reply.response_en;
}

export function ConversationThread({
    conversation,
    insertedMessage,
    canReply,
    canManage,
    quickReplies,
}: ConversationThreadProps) {
    const { t, locale } = useTranslation();
    const form = useForm({ message: '' });
    const closeForm = useForm({ message: '', conversation: '' });
    const acceptForm = useForm({ conversation: '' });
    const messagesContainerRef = useRef<HTMLDivElement | null>(null);
    const previousConversationIdRef = useRef<number | null>(null);
    const previousLastMessageIdRef = useRef<number | undefined>(undefined);
    const wasNearBottomRef = useRef(true);
    const pendingAgentSendRef = useRef(false);
    const [sendFailed, setSendFailed] = useState(false);
    const [closeDialogOpen, setCloseDialogOpen] = useState(false);
    const [closingLanguage, setClosingLanguage] = useState<ReplyLanguage>(
        conversation?.language === 'my' || conversation?.language === 'zh'
            ? conversation.language
            : locale === 'my' || locale === 'zh'
              ? locale
              : 'en',
    );
    const closingReplies = useMemo(() => quickReplies.filter((reply) => reply.category === 'closing'), [quickReplies]);
    const [selectedClosingReplyId, setSelectedClosingReplyId] = useState<number | undefined>(closingReplies[0]?.id);
    const selectedClosingReply = closingReplies.find((reply) => reply.id === selectedClosingReplyId);
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

    const closeConversation = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!conversation || !closeForm.data.message.trim()) return;

        closeForm.transform((data) => ({ message: data.message.trim() }));
        closeForm.post(`/support/conversations/${conversation.id}/close`, {
            preserveScroll: true,
            onSuccess: () => {
                setCloseDialogOpen(false);
                closeForm.reset();
            },
        });
    };

    const isLive = conversation.status === 'open' || conversation.status === 'with_agent';

    const acceptConversation = () => {
        acceptForm.post(`/support/conversations/${conversation.id}/accept`, {
            preserveScroll: true,
        });
    };

    const openCloseDialog = () => {
        closeForm.setData('message', selectedClosingReply ? localizedReply(selectedClosingReply, closingLanguage) : '');
        closeForm.clearErrors();
        setCloseDialogOpen(true);
    };

    const selectClosingReply = (value: string) => {
        const replyId = Number(value);
        const reply = closingReplies.find((item) => item.id === replyId);

        setSelectedClosingReplyId(replyId);
        if (reply) {
            closeForm.setData('message', localizedReply(reply, closingLanguage));
            closeForm.clearErrors();
        }
    };

    const selectClosingLanguage = (value: string) => {
        if (value !== 'en' && value !== 'my' && value !== 'zh') return;

        setClosingLanguage(value);
        if (selectedClosingReply) {
            closeForm.setData('message', localizedReply(selectedClosingReply, value));
            closeForm.clearErrors();
        }
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
                            #{conversation.id}{' '}
                            {conversation.agent?.username ?? t('support.chat_conversations.unassigned')}
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 font-medium',
                                    conversation.status === 'closed'
                                        ? 'bg-muted text-muted-foreground'
                                        : conversation.status === 'waiting_agent'
                                          ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                          : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
                                )}
                            >
                                {t(
                                    `support.chat_conversations.${conversation.status === 'waiting_agent' ? 'pending' : conversation.status === 'with_agent' ? 'open' : conversation.status}`,
                                )}
                            </span>
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {canReply && conversation.status === 'waiting_agent' ? (
                        <Button type="button" size="sm" disabled={acceptForm.processing} onClick={acceptConversation}>
                            <UserCheckIcon />
                            {t('support.chat_conversations.accept')}
                        </Button>
                    ) : null}
                    {canManage && canReply && conversation.status === 'open' ? (
                        <Button type="button" size="sm" variant="outline" onClick={openCloseDialog}>
                            <LockKeyholeIcon />
                            {t('support.chat_conversations.close')}
                        </Button>
                    ) : null}
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
            {acceptForm.errors.conversation ? (
                <p className="border-t border-border px-4 py-2 text-xs text-danger" role="alert">
                    {acceptForm.errors.conversation}
                </p>
            ) : null}
            {canReply && isLive ? (
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
            ) : conversation.status === 'closed' ? (
                <p className="border-t border-border bg-muted/30 px-4 py-3 text-center text-xs text-muted-foreground">
                    {t('support.chat_conversations.closed_read_only')}
                </p>
            ) : !isLive ? (
                <p className="border-t border-border bg-muted/30 px-4 py-3 text-center text-xs text-muted-foreground">
                    {t(
                        conversation.status === 'waiting_agent'
                            ? 'support.chat_conversations.accept_to_reply'
                            : 'support.chat_conversations.in_chat_flow',
                    )}
                </p>
            ) : null}
            <Dialog open={closeDialogOpen} onOpenChange={setCloseDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('support.chat_conversations.close')}</DialogTitle>
                        <DialogDescription>
                            {t('support.chat_conversations.close_description').replace(':id', String(conversation.id))}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={closeConversation} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="closing-language">{t('support.quick_replies.language')}</Label>
                            <Select value={closingLanguage} onValueChange={selectClosingLanguage}>
                                <SelectTrigger id="closing-language">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="en">{t('support.quick_replies.english')}</SelectItem>
                                    <SelectItem value="my">{t('support.quick_replies.myanmar')}</SelectItem>
                                    <SelectItem value="zh">{t('support.quick_replies.chinese')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="closing-message">{t('support.chat_conversations.final_message')}</Label>
                            <Textarea
                                id="closing-message"
                                value={closeForm.data.message}
                                maxLength={2000}
                                onChange={(event) => {
                                    closeForm.setData('message', event.target.value);
                                    closeForm.clearErrors('message');
                                }}
                                aria-invalid={Boolean(closeForm.errors.message)}
                                required
                            />
                            {closeForm.errors.message ? (
                                <p className="text-xs text-danger" role="alert">
                                    {closeForm.errors.message}
                                </p>
                            ) : null}
                            {closeForm.errors.conversation ? (
                                <p className="text-xs text-danger" role="alert">
                                    {closeForm.errors.conversation}
                                </p>
                            ) : null}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={closeForm.processing}
                                onClick={() => setCloseDialogOpen(false)}
                            >
                                {t('support.chat_conversations.cancel')}
                            </Button>
                            <Button type="submit" disabled={closeForm.processing || !closeForm.data.message.trim()}>
                                <LockKeyholeIcon />
                                {closeForm.processing
                                    ? t('support.chat_conversations.closing')
                                    : t('support.chat_conversations.send_and_close')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </main>
    );
}
