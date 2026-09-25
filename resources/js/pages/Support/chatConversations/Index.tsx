import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { ConversationList } from './ConversationList';
import { ConversationThread } from './ConversationThread';
import { QuickRepliesPanel } from './QuickRepliesPanel';
import type { ChatFilters, Conversation, ConversationSummary, QuickReply } from './types';

type IndexProps = {
    conversations: ConversationSummary[];
    selectedConversation: Conversation | null;
    quickReplies: QuickReply[];
    filters: ChatFilters;
};

export default function Index({ conversations, selectedConversation, quickReplies, filters }: IndexProps) {
    const [message, setMessage] = useState('');

    return (
        <>
            <Head title={'Chat Conversations'} />
            <PageContent className="h-full min-h-0 gap-3 overflow-hidden">
                <PageHeader />
                <div className="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)] overflow-hidden rounded-lg border border-border bg-background shadow-sm lg:grid-cols-[250px_minmax(0,1fr)_500px] lg:grid-rows-none">
                    <ConversationList
                        conversations={conversations}
                        filters={filters}
                        selectedId={selectedConversation?.id}
                    />
                    <ConversationThread conversation={selectedConversation} insertedMessage={message} />
                    <QuickRepliesPanel
                        quickReplies={quickReplies}
                        conversation={selectedConversation}
                        onUse={setMessage}
                    />
                </div>
            </PageContent>
        </>
    );
}
