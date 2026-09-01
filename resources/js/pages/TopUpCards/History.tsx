import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import type { Paginated } from '@/components/Pagination';
import { TopUpCardRedeemRecent } from '@/components/top-up-cards/TopUpCardRedeemRecent';
import { TopUpCardRedeemStats } from '@/components/top-up-cards/TopUpCardRedeemStats';
import { TopUpCardRedeemTable } from '@/components/top-up-cards/TopUpCardRedeemTable';
import { useTranslation } from '@/hooks/useTranslation';
import type { RedeemHistoryFilters, RedeemHistoryStats, TopUpCardRow } from '@/lib/top-up-cards';

type HistoryProps = {
    cards: Paginated<TopUpCardRow>;
    recent: TopUpCardRow[];
    amounts: string[];
    stats: RedeemHistoryStats;
    filters: RedeemHistoryFilters;
};

function visitHistory(filters: RedeemHistoryFilters, onStart?: () => void, onFinish?: () => void) {
    router.get('/top-up-cards/redeem-history', {
        search: filters.search || undefined,
        amount: filters.amount || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
        sort: filters.sort,
        direction: filters.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart,
        onFinish,
    });
}

export default function TopUpCardsHistory({ cards, recent = [], amounts, stats, filters }: HistoryProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [tableLoading, setTableLoading] = useState(false);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const filterTable = (next: RedeemHistoryFilters) => {
        visitHistory(next, () => setTableLoading(true), () => setTableLoading(false));
    };

    return (
        <>
            <Head title={t('menu.redeem_history')} />
            <PageContent>
                <PageHeader />
                <TopUpCardRedeemStats stats={stats} />
                <TopUpCardRedeemRecent recent={recent} />
                <TopUpCardRedeemTable
                    cards={cards}
                    amounts={amounts}
                    filters={filters}
                    search={search}
                    loading={tableLoading}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => filterTable({ ...filters, search: value }), 300);
                    }}
                    onFilter={filterTable}
                />
            </PageContent>
        </>
    );
}
