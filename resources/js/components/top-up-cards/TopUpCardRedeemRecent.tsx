import { HistoryIcon, UserRoundIcon } from 'lucide-react';

import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { formatTopUpAmount, formatTopUpDateTime, type TopUpCardRow } from '@/lib/top-up-cards';

export function TopUpCardRedeemRecent({ recent }: { recent: TopUpCardRow[] }) {
    const { t } = useTranslation();

    return (
        <section className="overflow-hidden rounded-[12px] border border-border/70 bg-card shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
            <div className="flex items-start gap-2.5 px-4 py-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                    <HistoryIcon className="size-4" strokeWidth={1.85} />
                </span>
                <div className="min-w-0">
                    <h2 className="text-[14px] font-semibold leading-none text-foreground">{t('top_up_cards.recent_title')}</h2>
                    <p className="mt-1 text-[12px] leading-4 text-muted-foreground">{t('top_up_cards.recent_description')}</p>
                </div>
            </div>
            {recent.length === 0 ? (
                <div className="flex min-h-[140px] flex-col items-center justify-center gap-1.5 px-4 pb-8 text-center">
                    <span className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <HistoryIcon className="size-[18px]" strokeWidth={1.8} />
                    </span>
                    <p className="text-[13px] font-semibold text-foreground">{t('top_up_cards.history_empty_title')}</p>
                    <p className="max-w-[280px] text-[11px] leading-4 text-muted-foreground">
                        {t('top_up_cards.history_empty_description')}
                    </p>
                </div>
            ) : (
                <div className="flex gap-2.5 overflow-x-auto px-4 pb-4">
                    {recent.map((card) => (
                        <article
                            key={card.id}
                            className="relative w-[200px] shrink-0 overflow-hidden rounded-[10px] border border-primary/20 bg-[linear-gradient(180deg,hsl(var(--primary)/0.10),hsl(var(--card)))] p-2.5 shadow-[0_4px_12px_rgb(23_50_54/0.08)] dark:shadow-[0_4px_12px_rgb(0_0_0/0.26)]"
                        >
                            <span className="absolute inset-y-0 left-0 w-[3px] bg-primary/60" aria-hidden />
                            <div className="flex items-start justify-between gap-2 pl-1.5">
                                <p className="truncate font-mono text-[10px] tracking-wide text-muted-foreground">
                                    {card.serial_no}
                                </p>
                                <StatusBadge status={card.status} className="text-[10px]" />
                            </div>
                            <p className="mt-2 pl-1.5 font-heading text-[16px] leading-none font-bold tabular-nums text-primary">
                                {formatTopUpAmount(card.amount)}
                            </p>
                            <div className="mt-2 ml-1.5 flex items-center gap-1.5 rounded-[6px] border border-dashed border-primary/25 bg-card/70 px-2 py-1 text-[11px] text-muted-foreground">
                                <UserRoundIcon className="size-3 shrink-0 text-primary" strokeWidth={1.9} />
                                <span className="truncate">{card.redeemed_by ?? t('top_up_cards.no_customer')}</span>
                            </div>
                            <p className="mt-1.5 pl-1.5 text-[10px] text-muted-foreground">
                                {formatTopUpDateTime(card.redeemed_at)}
                            </p>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
