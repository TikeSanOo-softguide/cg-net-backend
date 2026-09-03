import { useEffect, useMemo, useState } from 'react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlClass } from '@/lib/form-control';
import type { SupportedLocale } from '@/types';
import { cn } from '@/lib/utils';

const WEEK_DAYS = 7;

function localeTag(locale: SupportedLocale): string {
    if (locale === 'my') return 'my-MM';
    if (locale === 'zh') return 'zh-CN';
    return 'en-US';
}

function parseIsoDateTime(value: string): Date | null {
    const trimmed = value?.trim?.() ?? '';
    if (!trimmed) return null;

    // Accepts "YYYY-MM-DDTHH:mm:ss" or "YYYY-MM-DD HH:mm:ss"
    // Also tolerates missing seconds (treats as :00)
    const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/);
    if (match) {
        const [, y, m, d, h, min, sec = '0'] = match;
        const year = Number(y);
        const month = Number(m);
        const day = Number(d);
        const hours = Number(h);
        const minutes = Number(min);
        const seconds = Number(sec);

        const date = new Date(Date.UTC(year, month - 1, day, hours, minutes, seconds, 0));

        if (
            date.getUTCFullYear() !== year ||
            date.getUTCMonth() !== month - 1 ||
            date.getUTCDate() !== day ||
            date.getUTCHours() !== hours ||
            date.getUTCMinutes() !== minutes ||
            date.getUTCSeconds() !== seconds
        ) {
            return null;
        }

        return date;
    }

    const direct = new Date(trimmed);
    return Number.isNaN(direct.getTime()) ? null : direct;
}

function toIsoDateTime(date: Date): string {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    const hours = String(date.getUTCHours()).padStart(2, '0');
    const minutes = String(date.getUTCMinutes()).padStart(2, '0');
    const seconds = String(date.getUTCSeconds()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
}

/** Convert Date → "HH:mm:ss" for native <input type="time"> */
function toTimeValue(date: Date): string {
    const hours = String(date.getUTCHours()).padStart(2, '0');
    const minutes = String(date.getUTCMinutes()).padStart(2, '0');
    const seconds = String(date.getUTCSeconds()).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
}

function startOfDay(date: Date): Date {
    return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
}

function isSameDay(left: Date, right: Date): boolean {
    return (
        left.getUTCFullYear() === right.getUTCFullYear() &&
        left.getUTCMonth() === right.getUTCMonth() &&
        left.getUTCDate() === right.getUTCDate()
    );
}

function weekdayLabels(tag: string): string[] {
    const formatter = new Intl.DateTimeFormat(tag, { weekday: 'narrow' });
    return Array.from({ length: WEEK_DAYS }, (_, index) => {
        const date = new Date(Date.UTC(2024, 8, index + 1));
        return formatter.format(date);
    });
}

function monthCells(view: Date): Array<Date | null> {
    const first = new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth(), 1));
    const daysInMonth = new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth() + 1, 0)).getUTCDate();
    const leading = first.getUTCDay();
    const cells: Array<Date | null> = Array.from({ length: leading }, () => null);

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth(), day)));
    }

    while (cells.length % WEEK_DAYS !== 0) {
        cells.push(null);
    }

    return cells;
}

type DateTimePickerProps = {
    id?: string;
    name?: string;
    value: string | null | undefined;
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

export function DateTimePicker({
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
}: DateTimePickerProps) {
    const { t, locale } = useTranslation();
    const [open, setOpen] = useState(false);

    const selected = value ? parseIsoDateTime(value) : null;
    const [view, setView] = useState(() => selected ?? new Date());

    // Local time value for the native <input type="time">
    const [timeValue, setTimeValue] = useState(() => (selected ? toTimeValue(selected) : ''));

    const tag = localeTag(locale);
    const labels = useMemo(() => weekdayLabels(tag), [tag]);
    const cells = useMemo(() => monthCells(view), [view]);

    const now = new Date();
    const today = startOfDay(now);

    const minDate = min ? parseIsoDateTime(min) : null;
    const maxDate = max ? parseIsoDateTime(max) : null;

    const nowDisabled = (minDate !== null && now < minDate) || (maxDate !== null && now > maxDate);

    const display = selected
        ? (() => {
            const datePart = new Intl.DateTimeFormat(tag, {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                timeZone: 'UTC',
            }).format(selected);

            const timePart = new Intl.DateTimeFormat(tag, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'UTC',
            }).format(selected);

            return `${datePart} ${timePart}`;
        })()
        : '';

    const monthLabel = new Intl.DateTimeFormat(tag, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(view);

    // Sync when picker opens or external value changes
    useEffect(() => {
        if (open) {
            const parsed = value ? parseIsoDateTime(value) : null;
            setView(parsed ?? new Date());
            setTimeValue(parsed ? toTimeValue(parsed) : '');
        }
    }, [open, value]);

    const isDisabledDay = (date: Date): boolean => {
        if (minDate) {
            const minDay = startOfDay(minDate);
            if (date < minDay) return true;
        }
        if (maxDate) {
            const maxDay = startOfDay(maxDate);
            if (date > maxDay) return true;
        }
        return false;
    };

    const applyDateTime = (datePart: Date, timeStr: string) => {
        // timeStr is "HH:mm" or "HH:mm:ss"
        const [h = '0', m = '0', s = '0'] = timeStr.split(':');
        const next = new Date(
            Date.UTC(
                datePart.getUTCFullYear(),
                datePart.getUTCMonth(),
                datePart.getUTCDate(),
                Number(h),
                Number(m),
                Number(s),
                0,
            ),
        );

        if (minDate && next < minDate) {
            onChange(toIsoDateTime(minDate));
            return;
        }
        if (maxDate && next > maxDate) {
            onChange(toIsoDateTime(maxDate));
            return;
        }

        onChange(toIsoDateTime(next));
    };

    const selectDate = (date: Date) => {
        const time = timeValue || '23:59:59';

        if (!timeValue) {
            setTimeValue(time);
        }

        applyDateTime(date, time);
    };

    const handleTimeChange = (nextTime: string) => {
        setTimeValue(nextTime);
        if (selected && nextTime) {
            applyDateTime(selected, nextTime);
        }
    };

    const selectNow = () => {
        const n = new Date();
        setTimeValue(toTimeValue(n));
        setView(n);
        onChange(toIsoDateTime(n));
        setOpen(false);
    };

    return (
        <DropdownMenu
            modal={false}
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) onBlur?.();
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
                    <span className="min-w-0 flex-1 truncate">
                        {display || placeholder || t('common.select_datetime')}
                    </span>
                </button>
            </DropdownMenuTrigger>

            {name ? <input type="hidden" name={name} value={value ?? ''} /> : null}

            <DropdownMenuContent
                align="start"
                className="z-[90] w-[280px] p-2.5"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                {/* Month navigation */}
                <div className="mb-2 flex items-center gap-1">
                    <button
                        type="button"
                        className="inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        onClick={() => setView(new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth() - 1, 1)))}
                        aria-label={t('common.previous_month')}
                    >
                        <ChevronLeftIcon className="size-4" strokeWidth={1.9} />
                    </button>
                    <p className="min-w-0 flex-1 text-center text-[13px] font-semibold text-foreground">{monthLabel}</p>
                    <button
                        type="button"
                        className="inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        onClick={() => setView(new Date(Date.UTC(view.getUTCFullYear(), view.getUTCMonth() + 1, 1)))}
                        aria-label={t('common.next_month')}
                    >
                        <ChevronRightIcon className="size-4" strokeWidth={1.9} />
                    </button>
                </div>

                {/* Calendar grid */}
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
                        if (!date) {
                            return <span key={`empty-${index}`} className="size-8" />;
                        }

                        const selectedDay = selected !== null && isSameDay(date, selected);
                        const isToday = isSameDay(date, today);
                        const dayDisabled = isDisabledDay(date);

                        return (
                            <button
                                key={toIsoDateTime(date).slice(0, 10)}
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
                                {date.getUTCDate()}
                            </button>
                        );
                    })}
                </div>

                {/* Native time input (same approach as shadcn) */}
                <div className="mt-3 border-t border-border/70 pt-3">
                    <input
                        type="time"
                        step="1"
                        value={timeValue}
                        onChange={(e) => handleTimeChange(e.target.value)}
                        className={cn(
                            'h-9 w-full rounded-[6px] border border-input bg-background px-3 text-center text-[13px] font-medium tabular-nums outline-none',
                            'appearance-none focus:ring-1 focus:ring-primary/40',
                            // Hide the browser's built-in clock icon
                            '[&::-webkit-calendar-picker-indicator]:hidden',
                            '[&::-webkit-calendar-picker-indicator]:appearance-none',
                        )}
                        aria-label={t('common.time')}
                    />
                </div>

                {/* Footer */}
                <div className="mt-2 flex items-center justify-between gap-2 border-t border-border/70 pt-2">
                    <button
                        type="button"
                        disabled={nowDisabled}
                        className="text-[11px] font-semibold text-primary transition-colors hover:text-primary/80 disabled:pointer-events-none disabled:opacity-40"
                        onClick={selectNow}
                    >
                        {t('common.now')}
                    </button>

                    {clearable ? (
                        <button
                            type="button"
                            disabled={!value}
                            className="text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                            onClick={() => {
                                onChange('');
                                setTimeValue('');
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
