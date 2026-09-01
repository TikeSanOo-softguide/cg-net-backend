import { useEffect, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarDaysIcon,
    CheckIcon,
    ChevronDownIcon,
    CirclePlusIcon,
    CoinsIcon,
    LayersIcon,
    MinusIcon,
    PlusIcon,
    TicketsIcon,
    TriangleAlertIcon,
} from 'lucide-react';

import { FormControl } from '@/components/ui/form-control';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { expiryDateIn, formatTopUpAmount, formatTopUpNumber, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';

const QUICK_EXPIRY_DAYS = [30, 60, 90];

const MAX_QUANTITY = 100;

function denominationTileClass(active: boolean, dashed = false): string {
    return cn(
        'group/tile relative overflow-hidden rounded-[10px] border p-2.5 transition-all duration-200',
        dashed && 'border-dashed',
        active
            ? 'border-primary bg-[color-mix(in_srgb,hsl(var(--primary))_14%,var(--surface))] shadow-[0_6px_16px_rgb(23_50_54/0.12)] dark:shadow-[0_6px_16px_rgb(0_0_0/0.32)]'
            : cn(
                dashed ? 'border-primary/30' : 'border-primary/12',
                'bg-[color-mix(in_srgb,hsl(var(--primary))_6%,var(--muted))] hover:-translate-y-px hover:border-primary/40 hover:bg-[color-mix(in_srgb,hsl(var(--primary))_10%,var(--surface))] hover:shadow-[0_6px_14px_rgb(23_50_54/0.08)]',
            ),
    );
}

type GenerateFormProps = {
    presets: number[];
    selected: Record<string, number>;
    customOpen: boolean;
    customValue: string;
    expiresAt: string;
    processing: boolean;
    error?: string;
    onToggle: (amount: number) => void;
    onQuantity: (amount: number, quantity: number) => void;
    onCustomOpen: (open: boolean) => void;
    onCustomValue: (value: string) => void;
    onExpiresAt: (value: string) => void;
};

function SectionHeading({ icon: Icon, title, hint }: { icon: LucideIcon; title: string; hint?: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className="flex size-6 shrink-0 items-center justify-center rounded-[6px] bg-primary/12 text-primary">
                <Icon className="size-3.5" strokeWidth={1.9} />
            </span>
            <div className="min-w-0">
                <p className="text-[12px] font-semibold leading-none text-foreground">{title}</p>
                {hint ? <p className="mt-1 text-[11px] leading-none text-muted-foreground">{hint}</p> : null}
            </div>
        </div>
    );
}

function QuantityStepper({
    quantity,
    processing,
    decreaseLabel,
    increaseLabel,
    onQuantity,
}: {
    quantity: number;
    processing: boolean;
    decreaseLabel: string;
    increaseLabel: string;
    onQuantity: (quantity: number) => void;
}) {
    const stepClass =
        'flex h-full w-6 shrink-0 items-center justify-center text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary disabled:pointer-events-none disabled:opacity-40';

    return (
        <div className="mt-2 flex h-6 items-center overflow-hidden rounded-[7px] border border-primary/20 bg-surface/90">
            <button
                type="button"
                disabled={processing || quantity <= 1}
                className={stepClass}
                onClick={() => onQuantity(Math.max(1, quantity - 1))}
                aria-label={decreaseLabel}
            >
                <MinusIcon className="size-3" strokeWidth={2.4} />
            </button>
            <input
                type="number"
                min={1}
                max={MAX_QUANTITY}
                value={quantity}
                disabled={processing}
                aria-label={increaseLabel}
                className="h-full w-full min-w-0 border-x border-primary/20 bg-transparent px-1 text-center text-[11px] font-semibold tabular-nums text-foreground outline-none [appearance:textfield] disabled:opacity-60 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                onChange={(event) => onQuantity(Math.min(MAX_QUANTITY, Math.max(1, Number(event.target.value) || 1)))}
            />
            <button
                type="button"
                disabled={processing || quantity >= MAX_QUANTITY}
                className={stepClass}
                onClick={() => onQuantity(Math.min(MAX_QUANTITY, quantity + 1))}
                aria-label={increaseLabel}
            >
                <PlusIcon className="size-3" strokeWidth={2.4} />
            </button>
        </div>
    );
}

export function TopUpCardGenerateForm({
    presets,
    selected,
    customOpen,
    customValue,
    expiresAt,
    processing,
    error,
    onToggle,
    onQuantity,
    onCustomOpen,
    onCustomValue,
    onExpiresAt,
}: GenerateFormProps) {
    const { t } = useTranslation();
    const [menuOpen, setMenuOpen] = useState(false);
    const entries = Object.entries(selected).filter(([, quantity]) => quantity > 0);
    const totalCards = entries.reduce((sum, [, quantity]) => sum + quantity, 0);
    const totalValue = entries.reduce((sum, [value, quantity]) => sum + Number(value) * quantity, 0);
    const customAmount = Number(customValue);
    const customActive = customOpen && customAmount >= 100;
    const customQuantity = selected[String(customAmount)] ?? 1;

    useEffect(() => {
        if (! customOpen) {
            setMenuOpen(false);
        }
    }, [customOpen]);

    return (
        <div className="flex flex-col gap-4">
            <div>
                <SectionHeading
                    icon={LayersIcon}
                    title={t('top_up_cards.denominations')}
                    hint={t('top_up_cards.denominations_hint')}
                />
                <div className="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {presets.map((amount) => {
                        const key = String(amount);
                        const quantity = selected[key] ?? 0;
                        const checked = quantity > 0;

                        return (
                            <div key={amount} className={denominationTileClass(checked)}>
                                <span
                                    className={cn(
                                        'absolute inset-y-0 left-0 w-[3px] transition-colors',
                                        checked ? 'bg-primary' : 'bg-primary/20 group-hover/tile:bg-primary/45',
                                    )}
                                    aria-hidden
                                />
                                <button
                                    type="button"
                                    disabled={processing}
                                    aria-pressed={checked}
                                    onClick={() => onToggle(amount)}
                                    className="flex w-full items-center gap-2 pl-1 text-left disabled:pointer-events-none disabled:opacity-60"
                                >
                                    <span
                                        className={cn(
                                            'flex size-7 shrink-0 items-center justify-center rounded-[8px] transition-colors',
                                            checked
                                                ? 'bg-primary text-primary-foreground shadow-[0_2px_6px_rgb(23_50_54/0.18)]'
                                                : 'bg-surface text-primary/70 group-hover/tile:bg-primary/12 group-hover/tile:text-primary',
                                        )}
                                    >
                                        <CoinsIcon className="size-3.5" strokeWidth={1.9} />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block truncate font-heading text-[15px] leading-none font-semibold tabular-nums',
                                                checked ? 'text-primary' : 'text-foreground',
                                            )}
                                        >
                                            {formatTopUpNumber(amount)}
                                        </span>
                                        <span className="mt-1 block text-[9px] font-semibold leading-none tracking-[0.14em] text-muted-foreground uppercase">
                                            {TOP_UP_CARD_CURRENCY}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'flex size-4.5 shrink-0 items-center justify-center rounded-full border transition-colors',
                                            checked
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-primary/25 bg-surface text-transparent group-hover/tile:border-primary/50',
                                        )}
                                    >
                                        <CheckIcon className="size-2.5" strokeWidth={3} />
                                    </span>
                                </button>
                                {checked ? (
                                    <QuantityStepper
                                        quantity={quantity}
                                        processing={processing}
                                        decreaseLabel={t('top_up_cards.decrease')}
                                        increaseLabel={t('top_up_cards.increase')}
                                        onQuantity={(next) => onQuantity(amount, next)}
                                    />
                                ) : null}
                            </div>
                        );
                    })}
                    <DropdownMenu
                        modal={false}
                        open={menuOpen}
                        onOpenChange={(open) => {
                            setMenuOpen(open);

                            if (open) {
                                onCustomOpen(true);
                            } else if (customAmount < 100) {
                                onCustomOpen(false);
                            }
                        }}
                    >
                        <div className={denominationTileClass(customActive, true)}>
                            {customActive ? (
                                <span className="absolute inset-y-0 left-0 w-[3px] bg-primary" aria-hidden />
                            ) : null}
                            <div className="flex items-center gap-2 pl-1">
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        disabled={processing}
                                        aria-label={t('top_up_cards.add_custom')}
                                        className="flex min-w-0 flex-1 items-center gap-2 text-left disabled:pointer-events-none disabled:opacity-60"
                                    >
                                        <span
                                            className={cn(
                                                'flex size-7 shrink-0 items-center justify-center rounded-[8px] transition-colors',
                                                customActive
                                                    ? 'bg-primary text-primary-foreground shadow-[0_2px_6px_rgb(23_50_54/0.18)]'
                                                    : 'bg-surface text-primary/70',
                                            )}
                                        >
                                            <CirclePlusIcon className="size-3.5" strokeWidth={1.9} />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            {customActive ? (
                                                <>
                                                    <span className="block truncate font-heading text-[15px] leading-none font-semibold tabular-nums text-primary">
                                                        {formatTopUpNumber(customAmount)}
                                                    </span>
                                                    <span className="mt-1 block text-[9px] font-semibold leading-none tracking-[0.14em] text-muted-foreground uppercase">
                                                        {TOP_UP_CARD_CURRENCY}
                                                    </span>
                                                </>
                                            ) : (
                                                <span className="block truncate text-[12px] font-medium text-foreground">
                                                    {t('top_up_cards.custom_amount')}
                                                </span>
                                            )}
                                        </span>
                                        <ChevronDownIcon
                                            className={cn(
                                                'size-3.5 shrink-0 text-muted-foreground transition-transform',
                                                menuOpen && 'rotate-180',
                                            )}
                                            strokeWidth={2}
                                        />
                                    </button>
                                </DropdownMenuTrigger>
                                {customActive ? (
                                    <button
                                        type="button"
                                        disabled={processing}
                                        aria-pressed
                                        aria-label={t('top_up_cards.add_custom')}
                                        className="flex size-4.5 shrink-0 items-center justify-center rounded-full border border-primary bg-primary text-primary-foreground disabled:pointer-events-none disabled:opacity-60"
                                        onClick={() => onCustomOpen(false)}
                                    >
                                        <CheckIcon className="size-2.5" strokeWidth={3} />
                                    </button>
                                ) : null}
                            </div>
                            {customActive ? (
                                <QuantityStepper
                                    quantity={customQuantity}
                                    processing={processing}
                                    decreaseLabel={t('top_up_cards.decrease')}
                                    increaseLabel={t('top_up_cards.increase')}
                                    onQuantity={(next) => onQuantity(customAmount, next)}
                                />
                            ) : null}
                        </div>
                        <DropdownMenuContent
                            align="start"
                            className="w-[220px] p-2.5"
                            onCloseAutoFocus={(event) => event.preventDefault()}
                        >
                            <p className="mb-1.5 text-[11px] font-medium text-foreground">
                                {t('top_up_cards.custom_amount')}
                            </p>
                            <FormControl icon={CoinsIcon} compact>
                                <Input
                                    id="custom-amount"
                                    type="number"
                                    min={100}
                                    value={customValue}
                                    disabled={processing}
                                    placeholder="2500"
                                    className="h-8 text-[13px]"
                                    onChange={(event) => onCustomValue(event.target.value)}
                                    onKeyDown={(event) => event.stopPropagation()}
                                />
                            </FormControl>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div>
                <FormField label={t('top_up_cards.expires_at')} htmlFor="expires_at" icon={CalendarDaysIcon} required>
                    <Input
                        id="expires_at"
                        type="date"
                        value={expiresAt}
                        disabled={processing}
                        className="h-9 text-[13px]"
                        onChange={(event) => onExpiresAt(event.target.value)}
                    />
                </FormField>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {QUICK_EXPIRY_DAYS.map((days) => {
                        const value = expiryDateIn(days);
                        const active = value === expiresAt;

                        return (
                            <button
                                key={days}
                                type="button"
                                disabled={processing}
                                aria-pressed={active}
                                onClick={() => onExpiresAt(value)}
                                className={cn(
                                    'inline-flex h-7 items-center rounded-full border px-2.5 text-[11px] font-medium transition-colors disabled:pointer-events-none disabled:opacity-60',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-primary/15 bg-[color-mix(in_srgb,hsl(var(--primary))_6%,var(--muted))] text-muted-foreground hover:border-primary/40 hover:text-primary',
                                )}
                            >
                                {t('top_up_cards.expires_in_days').replace(':days', String(days))}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div
                className={cn(
                    'rounded-[10px] border p-2.5 transition-colors',
                    totalCards > 0
                        ? 'border-primary/25 bg-[color-mix(in_srgb,hsl(var(--primary))_12%,var(--surface))]'
                        : 'border-primary/12 bg-[color-mix(in_srgb,hsl(var(--primary))_6%,var(--muted))]',
                )}
            >
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 text-[12px] font-semibold',
                            totalCards > 0 ? 'text-primary' : 'text-muted-foreground',
                        )}
                    >
                        <TicketsIcon className="size-3.5 shrink-0" strokeWidth={1.9} />
                        {t('top_up_cards.summary_cards').replace(':count', String(totalCards))}
                    </span>
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 text-[12px] font-semibold',
                            totalCards > 0 ? 'text-primary' : 'text-muted-foreground',
                        )}
                    >
                        <CoinsIcon className="size-3.5 shrink-0" strokeWidth={1.9} />
                        {t('top_up_cards.summary_value').replace(':value', formatTopUpAmount(totalValue))}
                    </span>
                </div>
                {entries.length > 0 ? (
                    <div className="mt-2 flex flex-wrap gap-1.5 border-t border-primary/15 pt-2">
                        {entries.map(([value, quantity]) => (
                            <span
                                key={value}
                                className="inline-flex items-center gap-1 rounded-full bg-card px-2 py-0.5 text-[10px] font-medium tabular-nums text-muted-foreground shadow-[0_1px_2px_rgb(23_50_54/0.06)]"
                            >
                                {formatTopUpNumber(value)}
                                <span className="font-semibold text-primary">× {quantity}</span>
                            </span>
                        ))}
                    </div>
                ) : null}
            </div>

            {error ? (
                <p className="flex items-center gap-1.5 rounded-[6px] bg-danger/10 px-2.5 py-2 text-[11px] font-medium text-danger">
                    <TriangleAlertIcon className="size-3.5 shrink-0" strokeWidth={2} />
                    {error}
                </p>
            ) : null}
        </div>
    );
}
