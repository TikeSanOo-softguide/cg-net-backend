import { useEffect, useState, type ReactNode } from 'react';
import {
    BanknoteIcon,
    CalendarDaysIcon,
    ChevronDownIcon,
    CirclePlusIcon,
    MinusIcon,
    PlusIcon,
    TicketsIcon,
    TriangleAlertIcon,
} from 'lucide-react';

import { FormControl } from '@/components/ui/form-control';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { expiryDateIn, formatTopUpAmount, formatTopUpNumber, isoDate, TOP_UP_CARD_CURRENCY } from '@/lib/top-up-cards';

const QUICK_EXPIRY_DAYS = [30, 60, 90];
const MAX_QUANTITY = 100;

function denominationCardClass(checked: boolean, dashed = false): string {
    return cn(
        'relative overflow-hidden rounded-md border px-2 py-1.5 transition-colors',
        dashed && 'border-dashed',
        checked
            ? 'border-primary/35 bg-primary/5 before:absolute before:inset-y-0 before:left-0 before:w-0.5 before:bg-primary'
            : cn(
                dashed ? 'border-primary/25 bg-background' : 'border-border/60 bg-background',
                'hover:border-primary/25 hover:bg-muted/20',
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
        'flex size-6 shrink-0 items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40';

    return (
        <div className="mt-1.5 flex h-6 items-center overflow-hidden rounded border border-border/70 bg-background">
            <button
                type="button"
                disabled={processing || quantity <= 1}
                className={stepClass}
                onClick={() => onQuantity(Math.max(1, quantity - 1))}
                aria-label={decreaseLabel}
            >
                <MinusIcon className="size-3" strokeWidth={2} />
            </button>
            <input
                type="number"
                min={1}
                max={MAX_QUANTITY}
                value={quantity}
                disabled={processing}
                aria-label={increaseLabel}
                className="h-full w-full min-w-0 border-x border-border bg-transparent px-1 text-center text-[11px] font-medium tabular-nums outline-none [appearance:textfield] disabled:opacity-60 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                onChange={(event) => onQuantity(Math.min(MAX_QUANTITY, Math.max(1, Number(event.target.value) || 1)))}
            />
            <button
                type="button"
                disabled={processing || quantity >= MAX_QUANTITY}
                className={stepClass}
                onClick={() => onQuantity(Math.min(MAX_QUANTITY, quantity + 1))}
                aria-label={increaseLabel}
            >
                <PlusIcon className="size-3" strokeWidth={2} />
            </button>
        </div>
    );
}

function AmountLabel({ amount, muted = false }: { amount: number; muted?: boolean }) {
    return (
        <span className="flex min-w-0 items-center gap-2 ps-1">
            <BanknoteIcon
                className={cn('size-3.5 shrink-0', muted ? 'text-muted-foreground' : 'text-primary')}
                strokeWidth={2}
            />
            <span className="min-w-0 text-left">
                <span className={cn('block truncate text-[13px] font-semibold leading-none tabular-nums', muted ? 'text-muted-foreground' : 'text-foreground')}>
                    {formatTopUpNumber(amount)}
                </span>
                <span className="mt-0.5 block text-[10px] leading-none text-muted-foreground">{TOP_UP_CARD_CURRENCY}</span>
            </span>
        </span>
    );
}

function SectionLabel({ icon: Icon, children }: { icon: typeof BanknoteIcon; children: ReactNode }) {
    return (
        <div className="flex items-center gap-1.5">
            <Icon className="size-3.5 shrink-0 text-primary" strokeWidth={2} />
            <span className="text-[12px] font-medium text-foreground">{children}</span>
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
        <div className="flex flex-col gap-3">
            <div>
                <SectionLabel icon={BanknoteIcon}>{t('top_up_cards.denominations')}</SectionLabel>
                <p className="mt-0.5 ps-5 text-[11px] text-muted-foreground">{t('top_up_cards.denominations_hint')}</p>
                <div className="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                    {presets.map((amount) => {
                        const key = String(amount);
                        const quantity = selected[key] ?? 0;
                        const checked = quantity > 0;

                        return (
                            <div key={amount} className={denominationCardClass(checked)}>
                                <button
                                    type="button"
                                    disabled={processing}
                                    aria-pressed={checked}
                                    onClick={() => onToggle(amount)}
                                    className="w-full text-left disabled:pointer-events-none disabled:opacity-60"
                                >
                                    <AmountLabel amount={amount} muted={! checked} />
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
                        <div className={denominationCardClass(customActive, true)}>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    disabled={processing}
                                    aria-label={t('top_up_cards.add_custom')}
                                    className="flex w-full items-center justify-between gap-2 ps-1 text-left disabled:pointer-events-none disabled:opacity-60"
                                >
                                    {customActive ? (
                                        <AmountLabel amount={customAmount} />
                                    ) : (
                                        <span className="flex min-w-0 items-center gap-2">
                                            <CirclePlusIcon className="size-3.5 shrink-0 text-muted-foreground" strokeWidth={2} />
                                            <span className="truncate text-[12px] text-muted-foreground">
                                                {t('top_up_cards.custom_amount')}
                                            </span>
                                        </span>
                                    )}
                                    <ChevronDownIcon
                                        className={cn('size-3 shrink-0 text-muted-foreground', menuOpen && 'rotate-180')}
                                        strokeWidth={2}
                                    />
                                </button>
                            </DropdownMenuTrigger>
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
                            className="w-[200px] p-2"
                            onCloseAutoFocus={(event) => event.preventDefault()}
                        >
                            <p className="mb-1.5 text-[11px] font-medium">{t('top_up_cards.custom_amount')}</p>
                            <FormControl icon={BanknoteIcon} compact>
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
                <div className="flex flex-wrap items-center gap-1.5">
                    <Label
                        htmlFor="expires_at"
                        className="inline-flex shrink-0 items-center gap-1 text-[12px] font-medium"
                    >
                        <CalendarDaysIcon className="size-3.5 shrink-0 text-primary" strokeWidth={2} />
                        {t('top_up_cards.expires_at')}
                        <span className="text-primary">*</span>
                    </Label>
                    <FormControl compact className="min-w-[130px] flex-1">
                        <DatePicker
                            id="expires_at"
                            value={expiresAt}
                            min={isoDate()}
                            disabled={processing}
                            required
                            clearable={false}
                            className="h-8 text-[13px]"
                            onChange={onExpiresAt}
                        />
                    </FormControl>
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
                                    'inline-flex h-8 shrink-0 items-center rounded border px-2.5 text-[11px] disabled:pointer-events-none disabled:opacity-60',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground',
                                )}
                            >
                                {t('top_up_cards.expires_in_days').replace(':days', String(days))}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="rounded-md border border-border/60 bg-muted/20 px-2.5 py-2">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-foreground">
                    <span className="inline-flex items-center gap-1">
                        <TicketsIcon className="size-3.5 shrink-0 text-primary" strokeWidth={2} />
                        {t('top_up_cards.summary_cards').replace(':count', String(totalCards))}
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <BanknoteIcon className="size-3.5 shrink-0 text-primary" strokeWidth={2} />
                        {t('top_up_cards.summary_value').replace(':value', formatTopUpAmount(totalValue))}
                    </span>
                </div>
                {entries.length > 0 ? (
                    <p className="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] tabular-nums text-muted-foreground">
                        {entries.map(([value, quantity]) => (
                            <span key={value}>
                                {formatTopUpNumber(value)} × {quantity}
                            </span>
                        ))}
                    </p>
                ) : null}
            </div>

            {error ? (
                <p className="flex items-center gap-1.5 text-[11px] text-danger">
                    <TriangleAlertIcon className="size-3.5 shrink-0" strokeWidth={2} />
                    {error}
                </p>
            ) : null}
        </div>
    );
}
