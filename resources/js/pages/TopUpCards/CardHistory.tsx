import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { CardHistoryFilters, type TopUpCardRow } from '@/lib/top-up-cards';
import { Ban, CalendarX, ClipboardCheck, Clock3, CreditCard, TicketIcon } from 'lucide-react';
import { StatCard } from '@/components/StatCard';
import { TopUpCardListTable } from '@/components/top-up-cards/TopUpCardListTable';

type Props = {
    cards: Paginated<TopUpCardRow>;
    generated: TopUpCardRow[];
    amounts: number[];
    batches: {
        id: number;
        batch_no: string;
    }[];
    filters: CardHistoryFilters;
    stats: {
        total: number;
        pending: number;
        active: number;
        used: number;
        expired: number;
        blocked: number;
    };
};

function visitIndex(filters: CardHistoryFilters, onStart?: () => void, onFinish?: () => void) {
    router.get(
        '/top-up-cards/card-history',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            batch: filters.batch || undefined,
            amount: filters.amount || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            sort: filters.sort || 'created_at',
            direction: filters.direction || 'desc',
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart,
            onFinish,
        },
    );
}

export default function CardHistory({ cards, batches, generated = [], amounts, stats, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [tableLoading, setTableLoading] = useState(false);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const generatedPins = useMemo(
        () => Object.fromEntries(generated.filter((card) => card.pin).map((card) => [card.id, card.pin as string])),
        [generated],
    );

    const cardList = [
        {
            key: 'cards.total',
            title: t('top_up_cards.total'),
            value: stats.total.toLocaleString(),
            icon: CreditCard,
        },
        {
            key: 'cards.pending',
            title: t('status.pending'),
            value: stats.active.toLocaleString(),
            icon: Clock3,
        },
        {
            key: 'cards.active',
            title: t('status.active'),
            value: stats.active.toLocaleString(),
            icon: ClipboardCheck,
        },
        {
            key: 'cards.used',
            title: t('status.used'),
            value: stats.used.toLocaleString(),
            icon: TicketIcon,
        },
        {
            key: 'cards.expired',
            title: t('status.expired'),
            value: stats.expired.toLocaleString(),
            icon: CalendarX,
        },
        {
            key: 'cards.blocked',
            title: t('status.blocked'),
            value: stats.blocked.toLocaleString(),
            icon: Ban,
        },
    ];

    const filterTable = (next: CardHistoryFilters) => {
        visitIndex(
            next,
            () => setTableLoading(true),
            () => setTableLoading(false),
        );
    };

    return (
        <>
            <Head title={t('menu.card_history')} />
            <PageContent>
                <PageHeader />
                <StatCard items={cardList} className="xl:grid-cols-6" />
                <div className="print:hidden">
                    <TopUpCardListTable
                        cards={cards}
                        amounts={amounts}
                        batches={batches}
                        filters={filters}
                        search={search}
                        loading={tableLoading}
                        onSearchChange={(value) => {
                            setSearch(value);
                            window.clearTimeout(debounce.current);

                            debounce.current = window.setTimeout(() => {
                                filterTable({
                                    ...filters,
                                    search: value,
                                });
                            }, 300);
                        }}
                        onFilter={filterTable}
                    />
                </div>
            </PageContent>
        </>
    );
}
