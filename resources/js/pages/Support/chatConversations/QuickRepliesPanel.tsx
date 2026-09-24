import { useState } from 'react';
import { BoltIcon, SearchIcon, SparklesIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import type { Conversation, QuickReply } from './types';

type QuickRepliesPanelProps = {
    quickReplies: QuickReply[];
    conversation: Conversation | null;
    onUse: (message: string) => void;
};

export function QuickRepliesPanel({ quickReplies, conversation, onUse }: QuickRepliesPanelProps) {
    const [search, setSearch] = useState('');
    const filteredReplies = quickReplies.filter((reply) =>
        `${reply.keyword} ${reply.response_en}`.toLowerCase().includes(search.toLowerCase()),
    );
    const useReply = async (reply: QuickReply) => {
        if (!conversation) return;
        const response = await fetch(`/support/conversations/${conversation.id}/quick-replies/${reply.id}`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.ok) onUse((await response.json()).message);
    };

    return (
        <aside className="flex min-h-0 min-w-0 flex-col overflow-hidden border-t border-border bg-muted/20 lg:border-l lg:border-t-0">
            <div className="border-b border-border px-4 py-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <BoltIcon className="size-4 text-primary" /> Quick replies
                </h2>
                <div className="relative mt-3">
                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search quick replies..."
                        className="h-9 pl-9 text-xs"
                    />
                </div>
            </div>
            <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                {filteredReplies.map((reply) => (
                    <article key={reply.id} className="rounded-lg border border-border bg-background p-3 shadow-sm">
                        <h3 className="flex items-center gap-1.5 text-xs font-semibold">
                            <SparklesIcon className="size-3.5 text-primary" /> {reply.keyword}
                        </h3>
                        <p className="mt-2 text-[11px] leading-relaxed text-muted-foreground">{reply.response_en}</p>
                        <Button
                            type="button"
                            size="sm"
                            className="mt-3 h-7 text-[11px]"
                            disabled={!conversation}
                            onClick={() => useReply(reply)}
                        >
                            Use
                        </Button>
                    </article>
                ))}
                {filteredReplies.length === 0 ? (
                    <p className="py-8 text-center text-xs text-muted-foreground">No quick replies found.</p>
                ) : null}
            </div>
        </aside>
    );
}
