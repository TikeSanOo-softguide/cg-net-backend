import { useRef, useState } from 'react';
import {
    BoltIcon,
    CreditCardIcon,
    PackageIcon,
    SearchIcon,
    SparklesIcon,
    UserRoundIcon,
    WifiIcon,
    WrenchIcon,
    XIcon,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

import type { Conversation, QuickReply } from './types';

type QuickRepliesPanelProps = {
    quickReplies: QuickReply[];
    categories: string[];
    conversation: Conversation | null;
    onUse: (message: string) => void;
};

type ReplyLanguage = 'en' | 'my' | 'zh';

const replyLanguages: ReplyLanguage[] = ['en', 'my', 'zh'];

const categoryIcons: Record<string, typeof WifiIcon> = {
    internet: WifiIcon,
    payment: CreditCardIcon,
    package: PackageIcon,
    technical: WrenchIcon,
    account: UserRoundIcon,
};

function replyText(reply: QuickReply, language: ReplyLanguage): string {
    if (language === 'my') {
        return reply.response_my;
    }

    if (language === 'zh') {
        return reply.response_zh;
    }

    return reply.response_en;
}

export function QuickRepliesPanel({ quickReplies, categories, conversation, onUse }: QuickRepliesPanelProps) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [language, setLanguage] = useState<ReplyLanguage>(locale === 'my' || locale === 'zh' ? locale : 'en');
    const languageTabRefs = useRef<Record<ReplyLanguage, HTMLButtonElement | null>>({
        en: null,
        my: null,
        zh: null,
    });
    const categoryLabel = (value: string) => t(`support.quick_replies.categories.${value}`);
    const query = search.trim().toLocaleLowerCase();
    const filteredReplies = quickReplies.filter((reply) => {
        const matchesCategory = category === 'all' || reply.category === category;
        const searchableText = `${reply.keyword} ${replyText(reply, language)} ${categoryLabel(reply.category)}`;

        return matchesCategory && searchableText.toLocaleLowerCase().includes(query);
    });

    const onLanguageTabKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>, current: ReplyLanguage) => {
        const currentIndex = replyLanguages.indexOf(current);
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % replyLanguages.length;
        else if (event.key === 'ArrowLeft')
            nextIndex = (currentIndex + replyLanguages.length - 1) % replyLanguages.length;
        else if (event.key === 'Home') nextIndex = 0;
        else if (event.key === 'End') nextIndex = replyLanguages.length - 1;
        else return;

        event.preventDefault();
        const nextLanguage = replyLanguages[nextIndex];
        setLanguage(nextLanguage);
        languageTabRefs.current[nextLanguage]?.focus();
    };

    return (
        <aside className="flex min-h-0 min-w-0 flex-col overflow-hidden border-t border-border bg-muted/20 lg:border-l lg:border-t-0">
            <div className="min-w-0 border-b border-border px-3 py-3">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <BoltIcon className="size-4 shrink-0 text-primary" aria-hidden="true" />
                    {t('support.quick_replies.panel_title')}
                </h2>
                <div className="@container mt-3 min-w-0">
                    <div className="grid min-w-0 grid-cols-1 gap-2 @[24rem]:grid-cols-[minmax(0,1fr)_minmax(8rem,0.8fr)]">
                        <div className="relative min-w-0">
                            <SearchIcon
                                className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={t('support.quick_replies.panel_search')}
                                aria-label={t('support.quick_replies.panel_search')}
                                className="h-9 w-full min-w-0 pr-9 pl-9 text-xs"
                            />
                            {search ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="absolute top-1/2 right-1 size-7 min-h-7 min-w-7 -translate-y-1/2"
                                    aria-label={t('support.quick_replies.clear_search')}
                                    title={t('support.quick_replies.clear_search')}
                                    onClick={() => setSearch('')}
                                >
                                    <XIcon aria-hidden="true" />
                                </Button>
                            ) : null}
                        </div>
                        <Select value={category} onValueChange={setCategory}>
                            <SelectTrigger
                                className="h-9 w-full min-w-0 text-xs"
                                aria-label={t('support.quick_replies.category')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('support.quick_replies.all_categories')}</SelectItem>
                                {categories.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {categoryLabel(value)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div
                    className="mt-3 flex min-w-0 border-b border-border"
                    role="tablist"
                    aria-label={t('support.quick_replies.language')}
                >
                    {replyLanguages.map((value) => {
                        const selected = language === value;
                        const labelKey = value === 'en' ? 'english' : value === 'my' ? 'myanmar' : 'chinese';

                        return (
                            <button
                                key={value}
                                ref={(element) => {
                                    languageTabRefs.current[value] = element;
                                }}
                                type="button"
                                role="tab"
                                id={`quick-reply-tab-${value}`}
                                aria-selected={selected}
                                aria-label={t(`support.quick_replies.${labelKey}`)}
                                aria-controls="quick-reply-results"
                                tabIndex={selected ? 0 : -1}
                                onClick={() => setLanguage(value)}
                                onKeyDown={(event) => onLanguageTabKeyDown(event, value)}
                                className={`min-w-0 flex-1 border-b-2 px-3 py-2 text-xs font-medium transition-colors ${
                                    selected
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                                }`}
                            >
                                {t(`support.quick_replies.${labelKey}`).toUpperCase()}
                            </button>
                        );
                    })}
                </div>
            </div>
            <div
                id="quick-reply-results"
                role="tabpanel"
                aria-labelledby={`quick-reply-tab-${language}`}
                className="min-h-0 min-w-0 flex-1 space-y-2 overflow-x-hidden overflow-y-auto p-2.5"
            >
                {filteredReplies.map((reply) => {
                    const CategoryIcon = categoryIcons[reply.category] ?? SparklesIcon;
                    const response = replyText(reply, language);

                    return (
                        <article
                            key={reply.id}
                            className="max-w-full rounded-md border border-border bg-background p-2.5"
                        >
                            <div className="flex min-w-0 items-start gap-2">
                                <CategoryIcon className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <h3 className="min-w-0 flex-1 break-words text-xs font-semibold text-foreground">
                                            {reply.keyword}
                                        </h3>
                                        <span className="max-w-[40%] shrink text-right text-[10px] text-muted-foreground">
                                            {categoryLabel(reply.category)}
                                        </span>
                                    </div>
                                    <p className="mt-1.5 line-clamp-4 break-words text-[11px] leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                        {response}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-2 h-7 text-[11px]"
                                        disabled={!conversation || conversation.status === 'closed'}
                                        onClick={() => onUse(response)}
                                    >
                                        {t('support.quick_replies.use')}
                                    </Button>
                                </div>
                            </div>
                        </article>
                    );
                })}
                {filteredReplies.length === 0 ? (
                    <p className="py-8 text-center text-xs text-muted-foreground">
                        {quickReplies.length === 0
                            ? t('support.quick_replies.panel_empty')
                            : t('support.quick_replies.no_matching_replies')}
                    </p>
                ) : null}
            </div>
        </aside>
    );
}
