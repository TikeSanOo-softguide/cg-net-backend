import { memo, useCallback, useState } from 'react';
import { DownloadIcon, EyeIcon, EyeOffIcon, PrinterIcon } from 'lucide-react';
import QRCode from 'react-qr-code';

import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import {
    formatTopUpNumber,
    formatTopUpPin,
    TOP_UP_CARD_CURRENCY,
    topUpCardQrValue,
    type TopUpCardRow,
} from '@/lib/top-up-cards';

type GeneratedBatchProps = {
    cards: TopUpCardRow[];
    onExport: () => void;
};

type BatchCardProps = {
    card: TopUpCardRow;
    open: boolean;
    qrTitle: string;
    expiresLabel: string;
    revealLabel: string;
    hideLabel: string;
    onTogglePin: (serialNo: string) => void;
};

const TopUpCardBatchItem = memo(function TopUpCardBatchItem({
    card,
    open,
    qrTitle,
    expiresLabel,
    revealLabel,
    hideLabel,
    onTogglePin,
}: BatchCardProps) {
    return (
        <article className="relative overflow-hidden rounded-[10px] border border-primary/20 bg-[linear-gradient(180deg,hsl(var(--primary)/0.10),hsl(var(--card)))] p-2.5 shadow-[0_4px_14px_rgb(23_50_54/0.09)] dark:shadow-[0_4px_14px_rgb(0_0_0/0.28)]">
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
                    <div className="mt-2 flex items-center justify-between gap-2 rounded-[6px] border border-dashed border-primary/25 bg-card/70 px-2 py-1 print:border-gray-400 print:bg-gray-50">
                        <p className="font-mono text-[12px] font-semibold tracking-wider text-foreground print:text-black">
                            <span className="print:hidden">
                                {open && card.pin ? formatTopUpPin(card.pin) : formatTopUpPin(null)}
                            </span>
                            <span className="hidden print:inline">
                                {card.pin ? formatTopUpPin(card.pin) : formatTopUpPin(null)}
                            </span>
                        </p>

                        {card.pin ? (
                            <button
                                type="button"
                                className="inline-flex size-6 shrink-0 items-center justify-center rounded-[5px] text-primary transition-colors hover:bg-primary/10 print:hidden"
                                onClick={() => onTogglePin(card.serial_no)}
                                aria-label={open ? hideLabel : revealLabel}
                            >
                                {open ? (
                                    <EyeOffIcon className="size-3.5" />
                                ) : (
                                    <EyeIcon className="size-3.5" />
                                )}
                            </button>
                        ) : null}
                    </div>
                    <p className="mt-1.5 text-[10px] text-muted-foreground">
                        {expiresLabel}: {card.expires_at ?? '—'}
                    </p>
                </div>
                <div className="shrink-0 rounded-[6px] bg-white p-1 shadow-[0_1px_2px_rgb(23_50_54/0.08)]">
                    <QRCode
                        value={topUpCardQrValue(card)}
                        size={68}
                        bgColor="#ffffff"
                        fgColor="#111827"
                        level="M"
                        title={qrTitle}
                        className="block size-[68px]"
                    />
                </div>
            </div>
        </article>
    );
});

export const TopUpCardGeneratedBatch = memo(function TopUpCardGeneratedBatch({
    cards,
    onExport,
}: GeneratedBatchProps) {
    const { t } = useTranslation();
    const [revealed, setRevealed] = useState<Record<string, boolean>>({});

    const onTogglePin = useCallback((serialNo: string) => {
        setRevealed((current) => ({ ...current, [serialNo]: !current[serialNo] }));
    }, []);

    if (cards.length === 0) {
        return (
            <div className="flex h-full min-h-[160px] flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border px-4 py-6 text-center">
                <p className="text-[13px] font-medium text-foreground">{t('top_up_cards.empty_batch_title')}</p>
                <p className="max-w-[260px] text-[11px] leading-4 text-muted-foreground">
                    {t('top_up_cards.empty_batch_description')}
                </p>
            </div>
        );
    }

    const qrTitle = t('top_up_cards.qr_code');
    const expiresLabel = t('top_up_cards.expires_at');
    const revealLabel = t('top_up_cards.reveal_pin');
    const hideLabel = t('top_up_cards.hide_pin');

    return (
        <div className="flex h-full min-h-0 flex-col gap-2.5">
            <div className="flex flex-wrap gap-1.5 print:hidden">
                <Button type="button" size="sm" variant="outline" className="h-7 text-[11px]" onClick={onExport}>
                    <DownloadIcon className="size-3.5" strokeWidth={1.9} />
                    {t('common.export')}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-[11px]"
                    onClick={() => window.print()}
                >
                    <PrinterIcon className="size-3.5" strokeWidth={1.9} />
                    {t('top_up_cards.print_all')}
                </Button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto print:h-auto print:overflow-visible">
                <div id="top-up-card-print-area">
                    <div className="top-up-card-grid grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        {cards.map((card) => (
                            <TopUpCardBatchItem
                                key={card.serial_no}
                                card={card}
                                open={Boolean(revealed[card.serial_no])}
                                qrTitle={qrTitle}
                                expiresLabel={expiresLabel}
                                revealLabel={revealLabel}
                                hideLabel={hideLabel}
                                onTogglePin={onTogglePin}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
});
