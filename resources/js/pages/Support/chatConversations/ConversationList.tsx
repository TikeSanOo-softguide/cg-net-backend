import { router } from '@inertiajs/react';
import { MessageCircleIcon, SearchIcon } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

import type { ChatFilters, ConversationSummary } from './types';

type ConversationListProps = { conversations: ConversationSummary[]; filters: ChatFilters; selectedId?: number };

const statusFilters = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'waiting_agent', label: 'Pending' },
    { value: 'closed', label: 'Closed' },
];

export function ConversationList({ conversations, filters, selectedId }: ConversationListProps) {
    const visit = (params: Record<string, string | undefined>) => {
        router.get(
            '/support/conversations',
            { ...filters, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <aside className="flex min-h-0 min-w-0 flex-col overflow-hidden border-b border-border bg-background lg:border-b-0 lg:border-r">
            <div className="border-b border-border px-4 py-4">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-foreground">Chat conversations</h2>
                    <MessageCircleIcon className="size-4 text-muted-foreground" />
                </div>
                <div className="relative">
                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        defaultValue={filters.search ?? ''}
                        onChange={(event) => visit({ search: event.target.value || undefined })}
                        placeholder="Search conversations..."
                        className="h-9 pl-9 text-xs"
                    />
                </div>
                <div className="mt-3 flex gap-1 overflow-x-auto pb-0.5">
                    {statusFilters.map((status) => (
                        <button
                            key={status.value}
                            type="button"
                            onClick={() => visit({ status: status.value || undefined })}
                            className={cn(
                                'shrink-0 rounded-md px-2.5 py-1.5 text-[11px] font-medium transition-colors',
                                (filters.status ?? '') === status.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/70',
                            )}
                        >
                            {status.label}
                        </button>
                    ))}
                </div>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
                {conversations.map((conversation) => (
                    <button
                        key={conversation.id}
                        type="button"
                        onClick={() =>
                            router.get(
                                '/support/conversations',
                                { conversation: conversation.id },
                                { preserveState: true, preserveScroll: true },
                            )
                        }
                        className={cn(
                            'flex w-full gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-muted/50',
                            selectedId === conversation.id && 'bg-primary/5 shadow-[inset_2px_0_0_hsl(var(--primary))]',
                        )}
                    >
                        <Avatar name={conversation.user?.name ?? 'Guest'} />
                        <span className="min-w-0 flex-1">
                            <span className="flex items-center justify-between gap-2">
                                <span className="truncate text-xs font-semibold text-foreground">
                                    {conversation.user?.name ?? 'Guest'}
                                </span>
                                <span className="shrink-0 text-[10px] text-muted-foreground">
                                    {formatTime(conversation.updated_at)}
                                </span>
                            </span>
                            <span className="mt-1 block truncate text-[11px] text-muted-foreground">
                                {conversation.latest_message?.message ?? 'No messages yet'}
                            </span>
                            <span className="mt-1.5 flex items-center gap-1.5 text-[10px]">
                                <StatusDot status={conversation.status} />
                                <span className="text-muted-foreground">{statusLabel(conversation.status)}</span>
                                {conversation.unread_count > 0 ? (
                                    <span className="ml-auto rounded-full bg-primary px-1.5 py-0.5 font-semibold text-primary-foreground">
                                        {conversation.unread_count}
                                    </span>
                                ) : null}
                            </span>
                        </span>
                    </button>
                ))}
                {conversations.length === 0 ? (
                    <p className="px-4 py-8 text-center text-xs text-muted-foreground">No conversations found.</p>
                ) : null}
            </div>
        </aside>
    );
}

export function Avatar({ name, small = false }: { name: string; small?: boolean }) {
    const initials = name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary',
                small ? 'size-7 text-[10px]' : 'size-8 text-[11px]',
            )}
        >
            {initials}
        </span>
    );
}

function StatusDot({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'size-1.5 rounded-full',
                status === 'closed'
                    ? 'bg-muted-foreground/40'
                    : status === 'waiting_agent'
                      ? 'bg-amber-400'
                      : 'bg-emerald-400',
            )}
        />
    );
}

function statusLabel(status: string) {
    return status === 'waiting_agent' ? 'Pending' : status === 'closed' ? 'Closed' : status === 'bot' ? 'Bot' : 'Open';
}

export function formatTime(value: string | null | undefined) {
    if (!value) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}

export function formatDateTime(value: string) {
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}
