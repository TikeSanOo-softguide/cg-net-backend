import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { SparklesIcon, TicketIcon, TicketsIcon } from 'lucide-react';

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

type GenerateProps = {
    cards: Paginated<TopUpCardRow>;
    generated: TopUpCardRow[];
    presets: number[];
    amounts: string[];
    filters: TopUpCardFilters;
};

function visitIndex(filters: TopUpCardFilters, onStart?: () => void, onFinish?: () => void) {
    router.get('/top-up-cards/batch', {
        search: filters.search || undefined,
        status: filters.status || undefined,
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

export default function TopUpCardsGenerate({ cards, generated = [], presets, amounts, filters }: GenerateProps) {
    const { t } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<Record<string, number>>({});
    const [customOpen, setCustomOpen] = useState(false);
    const [customValue, setCustomValue] = useState('');
    const [tableLoading, setTableLoading] = useState(false);
    const debounce = useRef<number>(0);
    const form = useForm({
        amounts: [] as { value: number; quantity: number }[],
        expires_at: defaultExpiryDate(),
    });

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const generatedPins = useMemo(
        () => Object.fromEntries(generated.filter((card) => card.pin).map((card) => [card.id, card.pin as string])),
        [generated],
    );

    const applyCustom = (next: Record<string, number>, value: string, open: boolean) => {
        const copy = { ...next };

        Object.keys(copy).forEach((key) => {
            if (! presets.includes(Number(key))) {
                delete copy[key];
            }
        });

        if (open && Number(value) >= 100) {
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
            expires_at: form.data.expires_at,
        }));
        form.post('/top-up-cards/batch', {
            preserveScroll: true,
            onSuccess: () => {
                setSelected({});
                setCustomOpen(false);
                setCustomValue('');
            },
        });
    };

    const filterTable = (next: TopUpCardFilters) => {
        visitIndex(next, () => setTableLoading(true), () => setTableLoading(false));
    };

    const canGenerate = can('top-up-cards.create') && Object.values(selected).some((quantity) => quantity > 0);

    return (
        <>
            <Head title={t('menu.top_up_card_batch')} />
            <PageContent>
                <PageHeader />
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2 print:block">
                    <Card className="gap-4 py-4 print:hidden">
                        <CardHeader className="flex flex-row items-start gap-2.5">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                                <TicketIcon className="size-4" strokeWidth={1.85} />
                            </span>
                            <div className="min-w-0">
                                <CardTitle className="text-[14px]">{t('top_up_cards.generate_title')}</CardTitle>
                                <CardDescription className="text-[12px] leading-4">
                                    {t('top_up_cards.generate_description')}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <TopUpCardGenerateForm
                                    presets={presets}
                                    selected={selected}
                                    customOpen={customOpen}
                                    customValue={customValue}
                                    expiresAt={form.data.expires_at}
                                    processing={form.processing}
                                    error={form.errors.amounts ?? form.errors.expires_at}
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
                                />
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={! canGenerate || form.processing}
                                    className="h-9 w-full text-[13px] shadow-[0_4px_12px_rgb(23_50_54/0.14)] transition-shadow hover:shadow-[0_6px_16px_rgb(23_50_54/0.2)] disabled:shadow-none"
                                >
                                    {form.processing ? (
                                        <Spinner size="xs" className="text-current" />
                                    ) : (
                                        <SparklesIcon className="size-3.5" strokeWidth={1.9} />
                                    )}
                                    {form.processing ? t('top_up_cards.generating') : t('top_up_cards.generate')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card className="gap-4 py-4 print:border-0 print:shadow-none">
                        <CardHeader className="flex flex-row items-start gap-2.5 print:px-0">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                                <TicketsIcon className="size-4" strokeWidth={1.85} />
                            </span>
                            <div className="min-w-0">
                                <CardTitle className="text-[14px]">{t('top_up_cards.batch_title')}</CardTitle>
                                <CardDescription className="text-[12px] leading-4">
                                    {t('top_up_cards.batch_description')}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="relative print:px-0">
                            {form.processing ? (
                                <SpinnerOverlay className="relative inset-auto min-h-[220px]" label={t('top_up_cards.generating')} />
                            ) : (
                                <TopUpCardGeneratedBatch
                                    cards={generated}
                                    onExport={() => {
                                        window.location.href = '/top-up-cards/export';
                                    }}
                                />
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
                        onSearchChange={(value) => {
                            setSearch(value);
                            window.clearTimeout(debounce.current);
                            debounce.current = window.setTimeout(() => filterTable({ ...filters, search: value }), 300);
                        }}
                        onFilter={filterTable}
                    />
                </div>
            </PageContent>
        </>
    );
}
