import { router, useForm } from '@inertiajs/react';
import { MoreHorizontalIcon, PaperclipIcon, SendIcon, SmileIcon, UserRoundPlusIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

import { Avatar, formatDateTime } from './ConversationList';
import type { Conversation } from './types';

type ConversationThreadProps = { conversation: Conversation | null; insertedMessage?: string };

export function ConversationThread({ conversation, insertedMessage }: ConversationThreadProps) {
    const form = useForm({ message: '' });

    useEffect(() => {
        if (insertedMessage) form.setData('message', insertedMessage);
    }, [insertedMessage]);

    if (!conversation)
        return (
            <main className="flex min-h-0 items-center justify-center overflow-hidden p-6 text-sm text-muted-foreground">
                Select a conversation to begin.
            </main>
        );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!form.data.message.trim()) return;
        form.post(`/support/conversations/${conversation.id}/messages`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <main className="flex min-h-0 min-w-0 flex-col overflow-hidden bg-background">
            <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div className="flex min-w-0 items-center gap-3">
                    <Avatar name={conversation.user?.name ?? 'Guest'} />
                    <div className="min-w-0">
                        <h2 className="truncate text-sm font-semibold">{conversation.user?.name ?? 'Guest'}</h2>
                        <p className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            #{conversation.id} <span className="size-1.5 rounded-full bg-emerald-400" />{' '}
                            {conversation.agent?.username ?? 'Unassigned'}
                        </p>
                    </div>
                </div>
            </header>
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                {conversation.messages.map((message) => {
                    const isAgent = message.sender_type === 'agent';
                    return (
                        <div
                            key={message.id}
                            className={cn('flex items-end gap-2', isAgent ? 'justify-end' : 'justify-start')}
                        >
                            {!isAgent ? <Avatar name={conversation.user?.name ?? 'Guest'} small /> : null}
                            <div className={cn('max-w-[82%] sm:max-w-[70%]', isAgent ? 'items-end' : 'items-start')}>
                                <div
                                    className={cn(
                                        'rounded-xl px-3.5 py-2.5 text-xs leading-relaxed',
                                        isAgent
                                            ? 'rounded-br-sm bg-primary text-primary-foreground'
                                            : 'rounded-bl-sm bg-muted text-foreground',
                                    )}
                                >
                                    {message.message || 'Attachment'}
                                </div>
                                <p className="mt-1 text-[10px] text-muted-foreground">
                                    {formatDateTime(message.created_at)}
                                </p>
                            </div>
                        </div>
                    );
                })}
            </div>
            <form onSubmit={submit} className="border-t border-border p-3 sm:p-4">
                <div className="flex items-end gap-2 rounded-lg border border-border bg-background p-2 focus-within:ring-2 focus-within:ring-ring/30">
                    <Button type="button" variant="ghost" size="icon" className="size-8 min-h-8">
                        <PaperclipIcon />
                    </Button>
                    <Input
                        value={form.data.message}
                        onChange={(event) => form.setData('message', event.target.value)}
                        placeholder="Type your message..."
                        className="h-8 min-h-8 flex-1 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0"
                    />
                    <Button type="button" variant="ghost" size="icon" className="size-8 min-h-8">
                        <SmileIcon />
                    </Button>
                    <Button type="submit" size="sm" disabled={form.processing || !form.data.message.trim()}>
                        <SendIcon /> Send
                    </Button>
                </div>
                <p className="mt-1 text-right text-[10px] text-muted-foreground">{form.data.message.length}/2000</p>
            </form>
        </main>
    );
}
