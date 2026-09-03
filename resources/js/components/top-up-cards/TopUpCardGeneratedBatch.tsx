import { useState } from 'react';
import { CopyIcon, DownloadIcon, EyeIcon, EyeOffIcon, PrinterIcon } from 'lucide-react';
import QRCode from 'react-qr-code';

import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { toast } from '@/hooks/use-toast';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpNumber, TOP_UP_CARD_CURRENCY, topUpCardQrValue, type TopUpCardRow } from '@/lib/top-up-cards';

type GeneratedBatchProps = {
    cards: TopUpCardRow[];
    onExport: () => void;
};

export function TopUpCardGeneratedBatch({ cards, onExport }: GeneratedBatchProps) {
    const { t } = useTranslation();
    const [revealed, setRevealed] = useState<Record<number, boolean>>({});

    if (cards.length === 0) {
        return (
            <div className="flex min-h-[160px] flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border px-4 py-6 text-center">
                <p className="text-[13px] font-medium text-foreground">{t('top_up_cards.empty_batch_title')}</p>
                <p className="max-w-[260px] text-[11px] leading-4 text-muted-foreground">
                    {t('top_up_cards.empty_batch_description')}
                </p>
            </div>
        );
    }

    const copySerials = async () => {
        await navigator.clipboard.writeText(cards.map((card) => card.serial_no).join('\n'));
        toast({ variant: 'success', title: t('toast.success'), description: t('top_up_cards.copied_serials') });
    };

    return (
        <div className="flex flex-col gap-2.5">
            <div className="flex flex-wrap gap-1.5 print:hidden">
                <Button type="button" size="sm" variant="outline" className="h-7 text-[11px]" onClick={onExport}>
                    <DownloadIcon className="size-3.5" strokeWidth={1.9} />
                    {t('common.export')}
                </Button>
                <Button type="button" size="sm" variant="outline" className="h-7 text-[11px]" onClick={() => window.print()}>
                    <PrinterIcon className="size-3.5" strokeWidth={1.9} />
                    {t('top_up_cards.print_all')}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-[11px]"
                    onClick={() => void copySerials()}
                >
                    <CopyIcon className="size-3.5" strokeWidth={1.9} />
                    {t('top_up_cards.copy_serials')}
                </Button>
            </div>
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                {cards.map((card) => {
                    const open = Boolean(revealed[card.id]);

                    return (
                        <article
                            key={card.id}
                            className="relative overflow-hidden rounded-[10px] border border-primary/20 bg-[linear-gradient(180deg,hsl(var(--primary)/0.10),hsl(var(--card)))] p-2.5 shadow-[0_4px_14px_rgb(23_50_54/0.09)] dark:shadow-[0_4px_14px_rgb(0_0_0/0.28)]"
                        >
                            <span className="absolute inset-y-0 left-0 w-[3px] bg-primary/60" aria-hidden />
                            <div className="flex items-start gap-2.5 pl-1.5">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="truncate font-mono text-[10px] tracking-wide text-muted-foreground">
                                            {card.serial_no}
                                        </p>
                                        <StatusBadge status={card.status} className="text-[10px]" />
                                    </div>
                                    <p className="mt-2 flex items-baseline gap-1">
                                        <span className="font-heading text-lg leading-none font-bold tabular-nums text-primary">
                                            {formatTopUpNumber(card.amount)}
                                        </span>
                                        <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                                            {TOP_UP_CARD_CURRENCY}
                                        </span>
                                    </p>
                                    <div className="mt-2 flex items-center justify-between gap-2 rounded-[6px] border border-dashed border-primary/25 bg-card/70 px-2 py-1">
                                        <p className="font-mono text-[12px] tracking-wider text-foreground">
                                            {open && card.pin ? card.pin : '••••••'}
                                        </p>
                                        {card.pin ? (
                                            <button
                                                type="button"
                                                className="inline-flex size-6 shrink-0 items-center justify-center rounded-[5px] text-primary transition-colors hover:bg-primary/10 print:hidden"
                                                onClick={() => setRevealed((current) => ({ ...current, [card.id]: ! open }))}
                                                aria-label={open ? t('top_up_cards.hide_pin') : t('top_up_cards.reveal_pin')}
                                            >
                                                {open ? <EyeOffIcon className="size-3.5" /> : <EyeIcon className="size-3.5" />}
                                            </button>
                                        ) : null}
                                    </div>
                                    <p className="mt-1.5 text-[10px] text-muted-foreground">
                                        {t('top_up_cards.expires_at')}: {card.expires_at ?? '—'}
                                    </p>
                                </div>
                                <div className="shrink-0 rounded-[6px] bg-white p-1 shadow-[0_1px_2px_rgb(23_50_54/0.08)]">
                                    <QRCode
                                        value={topUpCardQrValue(card)}
                                        size={68}
                                        bgColor="#ffffff"
                                        fgColor="#111827"
                                        level="M"
                                        title={t('top_up_cards.qr_code')}
                                        className="block size-[68px]"
                                    />
                                </div>
                            </div>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
