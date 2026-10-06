import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useCan } from '@/hooks/useCan';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { ConversationList } from './ConversationList';
import { ConversationThread } from './ConversationThread';
import { QuickRepliesPanel } from './QuickRepliesPanel';
import type { ChatFilters, Conversation, ConversationSummary, QuickReply } from './types';

type IndexProps = {
    conversations: ConversationSummary[];
    selectedConversation: Conversation | null;
    quickReplies: QuickReply[];
    quickReplyCategories: string[];
    filters: ChatFilters;
};

export default function Index({
    conversations,
    selectedConversation,
    quickReplies,
    quickReplyCategories,
    filters,
}: IndexProps) {
    const { t } = useTranslation();
    const can = useCan();
    const canReply = can('support.create');
    const canManage = can('support.update');
    const showQuickReplies = canReply && selectedConversation?.status !== 'closed';
    const [message, setMessage] = useState('');

    useEffect(() => {
        if (!selectedConversation) return;

        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;

            router.get(
                '/support/conversations',
                {
                    conversation: selectedConversation.id,
                    search: filters.search,
                    status: filters.status,
                },
                {
                    only: ['selectedConversation'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 5000);

        return () => window.clearInterval(interval);
    }, [selectedConversation?.id, filters.search, filters.status]);

    return (
        <>
            <Head
                title={`${t('support.chat_conversations.app_name')} | ${t('support.chat_conversations.page_title')}`}
            />
            <PageContent className="h-full min-h-0 gap-3 overflow-hidden">
                <PageHeader />
                <div
                    className={cn(
                        'grid min-h-0 flex-1 overflow-hidden rounded-lg border border-border bg-background shadow-sm lg:grid-rows-none',
                        showQuickReplies
                            ? 'grid-rows-[minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)] lg:grid-cols-[minmax(12rem,0.8fr)_minmax(0,2fr)_minmax(14rem,1fr)]'
                            : 'grid-rows-[minmax(0,1fr)_minmax(0,1.5fr)] lg:grid-cols-[minmax(12rem,0.8fr)_minmax(0,2fr)]',
                    )}
                >
                    <ConversationList
                        conversations={conversations}
                        filters={filters}
                        selectedId={selectedConversation?.id}
                    />
                    <ConversationThread
                        conversation={selectedConversation}
                        insertedMessage={message}
                        canReply={canReply}
                        canManage={canManage}
                        quickReplies={quickReplies}
                    />
                    {showQuickReplies ? (
                        <QuickRepliesPanel
                            quickReplies={quickReplies}
                            categories={quickReplyCategories}
                            conversation={selectedConversation}
                            onUse={setMessage}
                        />
                    ) : null}
                </div>
            </PageContent>
        </>
    );
}
