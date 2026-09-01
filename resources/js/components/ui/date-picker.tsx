import { useEffect, useMemo, useState } from 'react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlClass } from '@/lib/form-control';
import type { SupportedLocale } from '@/types';
import { cn } from '@/lib/utils';

const WEEK_DAYS = 7;

function localeTag(locale: SupportedLocale): string {
    if (locale === 'my') {
        return 'my-MM';
    }

    if (locale === 'zh') {
        return 'zh-CN';
    }

    return 'en-US';
}

function parseIsoDate(value: string): Date | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function isSameDay(left: Date, right: Date): boolean {
    return left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate();
}

function weekdayLabels(tag: string): string[] {
    const formatter = new Intl.DateTimeFormat(tag, { weekday: 'narrow' });

    return Array.from({ length: WEEK_DAYS }, (_, index) => {
        const date = new Date(2024, 8, index + 1);

        return formatter.format(date);
    });
}

function monthCells(view: Date): Array<Date | null> {
    const first = new Date(view.getFullYear(), view.getMonth(), 1);
    const daysInMonth = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
    const leading = first.getDay();
    const cells: Array<Date | null> = Array.from({ length: leading }, () => null);

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(new Date(view.getFullYear(), view.getMonth(), day));
    }

    while (cells.length % WEEK_DAYS !== 0) {
        cells.push(null);
    }

    return cells;
}

type DatePickerProps = {
    id?: string;
    name?: string;
    value: string;
    min?: string;
    max?: string;
    disabled?: boolean;
    required?: boolean;
    clearable?: boolean;
    placeholder?: string;
    className?: string;
    'aria-invalid'?: boolean;
    onChange: (value: string) => void;
    onBlur?: () => void;
};

export function DatePicker({
    id,
    name,
    value,
    min,
    max,
    disabled = false,
    required = false,
    clearable = true,
    placeholder,
    className,
    'aria-invalid': ariaInvalid,
    onChange,
    onBlur,
}: DatePickerProps) {
    const { t, locale } = useTranslation();
    const [open, setOpen] = useState(false);
    const selected = parseIsoDate(value);
    const [view, setView] = useState(() => selected ?? new Date());
    const tag = localeTag(locale);
    const labels = useMemo(() => weekdayLabels(tag), [tag]);
    const cells = useMemo(() => monthCells(view), [view]);
    const today = startOfDay(new Date());
    const minDate = min ? parseIsoDate(min) : null;
    const maxDate = max ? parseIsoDate(max) : null;
    const todayDisabled =
        (minDate !== null && today < minDate) || (maxDate !== null && today > maxDate);
    const display = selected
        ? new Intl.DateTimeFormat(tag, { day: 'numeric', month: 'short', year: 'numeric' }).format(selected)
        : '';
    const monthLabel = new Intl.DateTimeFormat(tag, { month: 'long', year: 'numeric' }).format(view);

    useEffect(() => {
        if (open) {
            setView(parseIsoDate(value) ?? new Date());
        }
    }, [open, value]);

    const isDisabledDay = (date: Date): boolean => {
        if (minDate && date < minDate) {
            return true;
        }

        if (maxDate && date > maxDate) {
            return true;
        }

        return false;
    };

    const selectDate = (date: Date) => {
        onChange(toIsoDate(date));
        setOpen(false);
    };

    return (
        <DropdownMenu
            modal={false}
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (! next) {
                    onBlur?.();
                }
            }}
        >
            <DropdownMenuTrigger asChild disabled={disabled}>
                <button
                    id={id}
                    type="button"
                    disabled={disabled}
                    aria-invalid={ariaInvalid || undefined}
                    aria-required={required || undefined}
                    data-slot="input"
                    data-placeholder={display ? undefined : 'true'}
                    className={cn(
                        'flex h-10 items-center py-2 text-left',
                        formControlClass,
                        'data-[placeholder=true]:text-muted-foreground',
                        className,
                    )}
                >
                    <span className="min-w-0 flex-1 truncate">{display || placeholder || t('common.select_date')}</span>
                </button>
            </DropdownMenuTrigger>
            {name ? <input type="hidden" name={name} value={value} /> : null}
            <DropdownMenuContent
                align="start"
                className="z-[90] w-[252px] p-2.5"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <div className="mb-2 flex items-center gap-1">
                    <button
                        type="button"
                        className="inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        onClick={() => setView(new Date(view.getFullYear(), view.getMonth() - 1, 1))}
                        aria-label={t('common.previous_month')}
                    >
                        <ChevronLeftIcon className="size-4" strokeWidth={1.9} />
                    </button>
                    <p className="min-w-0 flex-1 text-center text-[13px] font-semibold text-foreground">{monthLabel}</p>
                    <button
                        type="button"
                        className="inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        onClick={() => setView(new Date(view.getFullYear(), view.getMonth() + 1, 1))}
                        aria-label={t('common.next_month')}
                    >
                        <ChevronRightIcon className="size-4" strokeWidth={1.9} />
                    </button>
                </div>
                <div className="grid grid-cols-7 gap-0.5">
                    {labels.map((label, index) => (
                        <span
                            key={`${label}-${index}`}
                            className="flex h-7 items-center justify-center text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            {label}
                        </span>
                    ))}
                    {cells.map((date, index) => {
                        if (! date) {
                            return <span key={`empty-${index}`} className="size-8" />;
                        }

                        const selectedDay = selected !== null && isSameDay(date, selected);
                        const isToday = isSameDay(date, today);
                        const dayDisabled = isDisabledDay(date);

                        return (
                            <button
                                key={toIsoDate(date)}
                                type="button"
                                disabled={dayDisabled}
                                onClick={() => selectDate(date)}
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-[6px] text-[12px] font-medium tabular-nums transition-colors',
                                    selectedDay
                                        ? 'bg-primary text-primary-foreground shadow-[0_4px_10px_hsl(var(--primary)/0.28)]'
                                        : isToday
                                            ? 'text-primary ring-1 ring-primary/40'
                                            : 'text-foreground hover:bg-primary/10 hover:text-primary',
                                    dayDisabled && 'pointer-events-none opacity-35',
                                )}
                            >
                                {date.getDate()}
                            </button>
                        );
                    })}
                </div>
                <div className="mt-2 flex items-center justify-between gap-2 border-t border-border/70 pt-2">
                    <button
                        type="button"
                        disabled={todayDisabled}
                        className="text-[11px] font-semibold text-primary transition-colors hover:text-primary/80 disabled:pointer-events-none disabled:opacity-40"
                        onClick={() => selectDate(today)}
                    >
                        {t('common.today')}
                    </button>
                    {clearable ? (
                        <button
                            type="button"
                            disabled={! value}
                            className="text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            onClick={() => {
                                onChange('');
                                setOpen(false);
                            }}
                        >
                            {t('common.clear')}
                        </button>
                    ) : null}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
