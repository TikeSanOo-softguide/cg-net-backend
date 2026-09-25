import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TopUpCardGenerateForm } from '@/components/top-up-cards/TopUpCardGenerateForm';
import { TopUpCardGeneratedBatch } from '@/components/top-up-cards/TopUpCardGeneratedBatch';
import { TopUpCardTable } from '@/components/top-up-cards/TopUpCardTable';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Spinner, SpinnerOverlay } from '@/components/ui/spinner';
import type { Paginated } from '@/components/Pagination';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { defaultExpiryDate, type TopUpCardFilters, type TopUpCardRow } from '@/lib/top-up-cards';

type GenerationState = {
    status?: 'processing' | 'completed' | 'failed' | null;
    message?: string | null;
    total_cards?: number;
    completed_chunks?: number;
    total_chunks?: number;
};

type GenerateProps = {
    cards: Paginated<TopUpCardRow>;
    generated: TopUpCardRow[];
    generation?: GenerationState;
    presets: number[];
    max_cards?: number;
    agents: { id: number; name: string }[];
    amounts: string[];
    filters: TopUpCardFilters;
};

const GENERATION_POLL_MS = 1500;

function visitIndex(filters: TopUpCardFilters, onStart?: () => void, onFinish?: () => void) {
    router.get(
        '/top-up-cards/batch',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            amount: filters.amount || undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
            sort: filters.sort,
            direction: filters.direction,
        },
        {
            only: ['cards', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart,
            onFinish,
        },
    );
}

export default function TopUpCardsGenerate({
    cards,
    generated = [],
    generation,
    presets,
    max_cards = 100000,
    agents = [],
    amounts,
    filters,
}: GenerateProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<Record<string, number>>({});
    const [selectedAgentIds, setSelectedAgentIds] = useState<string[]>([]);
    const [customOpen, setCustomOpen] = useState(false);
    const [customValue, setCustomValue] = useState('');
    const [tableLoading, setTableLoading] = useState(false);
    const debounce = useRef<number>(0);
    const pollInFlight = useRef(false);
    const form = useForm({
        amounts: [] as { value: number; quantity: number }[],
        agent_ids: [] as number[],
        expires_at: defaultExpiryDate(),
    });

    const generationStatus = generation?.status ?? null;
    const isGenerating = form.processing || generationStatus === 'processing';

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    useEffect(() => {
        if (generationStatus !== 'processing') {
            return;
        }

        const reloadGeneration = () => {
            if (pollInFlight.current || document.hidden) {
                return;
            }

            pollInFlight.current = true;
            router.reload({
                only: ['generated', 'generation', 'cards'],
                preserveUrl: true,
                onFinish: () => {
                    pollInFlight.current = false;
                },
            });
        };

        reloadGeneration();
        const intervalId = window.setInterval(reloadGeneration, GENERATION_POLL_MS);

        return () => {
            window.clearInterval(intervalId);
            pollInFlight.current = false;
        };
    }, [generationStatus]);

    const generatedPins = useMemo(
        () => Object.fromEntries(generated.filter((card) => card.pin).map((card) => [card.id, card.pin as string])),
        [generated],
    );

    const applyCustom = (next: Record<string, number>, value: string, open: boolean) => {
        const copy = { ...next };

        Object.keys(copy).forEach((key) => {
            if (!presets.includes(Number(key))) {
                delete copy[key];
            }
        });

        if (open && Number(value) >= 50) {
            copy[String(Number(value))] = copy[String(Number(value))] ?? 1;
        }

        return copy;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const amountsPayload = Object.entries(selected)
            .filter(([, quantity]) => quantity > 0)
            .map(([value, quantity]) => ({ value: Number(value), quantity }));

        form.transform(() => ({
            amounts: amountsPayload,
            agent_ids: selectedAgentIds.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0),
            expires_at: form.data.expires_at,
        }));
        form.post('/top-up-cards/batch', {
            preserveScroll: true,
            onSuccess: () => {
                setSelected({});
                setSelectedAgentIds([]);
                setCustomOpen(false);
                setCustomValue('');
            },
        });
    };

    const filterTable = useCallback((next: TopUpCardFilters) => {
        visitIndex(
            next,
            () => setTableLoading(true),
            () => setTableLoading(false),
        );
    }, []);

    const handleSearchChange = useCallback(
        (value: string) => {
            setSearch(value);
            window.clearTimeout(debounce.current);
            debounce.current = window.setTimeout(() => filterTable({ ...filters, search: value }), 300);
        },
        [filterTable, filters],
    );

    const selectedCards = Object.values(selected).reduce((sum, quantity) => sum + quantity, 0);
    const agentMultiplier = Math.max(1, selectedAgentIds.length || agents.length);
    const totalCards = selectedCards * agentMultiplier;
    const canGenerate =
        can('top-up-cards.create') && selectedCards > 0 && totalCards <= max_cards && !isGenerating;

    const handleExport = useCallback(() => {
        window.location.href = '/top-up-cards/export';
    }, []);

    const batchDescription =
        generationStatus === 'processing'
            ? t('top_up_cards.generating')
            : t('top_up_cards.batch_description');

    return (
        <>
            <Head title={t('menu.top_up_card_batch')} />
            <PageContent>
                <PageHeader />
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2 print:block">
                    <Card className="flex h-[520px] flex-col gap-3 py-4 print:hidden">
                        <CardHeader>
                            <CardTitle className="text-sm">{t('top_up_cards.generate_title')}</CardTitle>
                            <CardDescription className="text-[12px] leading-4">
                                {t('top_up_cards.generate_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-h-0 flex-1 overflow-y-auto">
                            <form onSubmit={submit} className="flex flex-col gap-3">
                                <TopUpCardGenerateForm
                                    agents={agents}
                                    selectedAgentIds={selectedAgentIds}
                                    presets={presets}
                                    maxCards={max_cards}
                                    selected={selected}
                                    customOpen={customOpen}
                                    customValue={customValue}
                                    expiresAt={form.data.expires_at}
                                    processing={isGenerating}
                                    error={form.errors.amounts ?? form.errors.agent_ids ?? form.errors.expires_at}
                                    onToggle={(amount) => {
                                        setSelected((current) => {
                                            const key = String(amount);

                                            if (current[key]) {
                                                const next = { ...current };
                                                delete next[key];

                                                return next;
                                            }

                                            return { ...current, [key]: 1 };
                                        });
                                    }}
                                    onQuantity={(amount, quantity) => {
                                        setSelected((current) => ({ ...current, [String(amount)]: quantity }));
                                    }}
                                    onCustomOpen={(open) => {
                                        setCustomOpen(open);
                                        setSelected((current) => applyCustom(current, customValue, open));
                                    }}
                                    onCustomValue={(value) => {
                                        setCustomValue(value);
                                        setSelected((current) => applyCustom(current, value, customOpen));
                                    }}
                                    onExpiresAt={(value) => form.setData('expires_at', value)}
                                    onAgentIds={setSelectedAgentIds}
                                />
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={!canGenerate || form.processing}
                                    className="h-8 w-full"
                                >
                                    {isGenerating ? <Spinner size="xs" className="text-current" /> : null}
                                    {isGenerating ? t('top_up_cards.generating') : t('top_up_cards.generate')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card className="flex h-[520px] flex-col gap-3 py-4 print:h-auto print:overflow-visible print:border-0 print:shadow-none">
                        <CardHeader className="print:px-0">
                            <CardTitle className="text-sm">{t('top_up_cards.batch_title')}</CardTitle>
                            <CardDescription className="text-[12px] leading-4">{batchDescription}</CardDescription>
                        </CardHeader>
                        <CardContent className="relative min-h-0 flex-1 overflow-hidden print:overflow-visible print:px-0">
                            {isGenerating && generated.length === 0 ? (
                                <SpinnerOverlay
                                    className="relative inset-auto min-h-[160px]"
                                    label={t('top_up_cards.generating')}
                                />
                            ) : (
                                <>
                                    {isGenerating ? (
                                        <SpinnerOverlay
                                            className="pointer-events-none"
                                            label={t('top_up_cards.generating')}
                                        />
                                    ) : null}
                                    <TopUpCardGeneratedBatch cards={generated} onExport={handleExport} />
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <div className="print:hidden">
                    <TopUpCardTable
                        cards={cards}
                        amounts={amounts}
                        filters={filters}
                        search={search}
                        generatedPins={generatedPins}
                        loading={tableLoading}
                        onSearchChange={handleSearchChange}
                        onFilter={filterTable}
                    />
                </div>
            </PageContent>
        </>
    );
}
