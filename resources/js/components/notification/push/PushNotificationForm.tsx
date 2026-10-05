import { FormEvent, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CalendarClockIcon, CalendarIcon, ClockIcon, LanguagesIcon, SendIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import {
    PUSH_TITLE_MAX_LENGTH,
    validatePushNotification,
    validatePushNotificationField,
    type PushNotificationFormValues,
} from '@/lib/push-notification-validation';
import { cn } from '@/lib/utils';

const emptyForm = (): PushNotificationFormValues => ({
    title_en: '',
    title_zh: '',
    title_my: '',
    schedule_date: '',
    schedule_time: '',
});

const TITLE_FIELDS = [
    { field: 'title_en', lang: 'en', badge: 'EN' },
    { field: 'title_zh', lang: 'zh', badge: 'ZH' },
    { field: 'title_my', lang: 'my', badge: 'MY' },
] as const;

const HOURS_12 = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));
const MINUTES = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));

type Period = 'AM' | 'PM';

type TimeParts12 = {
    hour12: string;
    minute: string;
    period: Period;
};

function splitTime12(value: string): TimeParts12 {
    const match = value.match(/^(\d{2}):(\d{2})$/);
    if (!match) {
        return { hour12: '', minute: '', period: 'AM' };
    }

    const hour24 = Number(match[1]);
    const minute = match[2];
    const period: Period = hour24 >= 12 ? 'PM' : 'AM';
    const hour12Num = hour24 % 12 === 0 ? 12 : hour24 % 12;

    return {
        hour12: String(hour12Num).padStart(2, '0'),
        minute,
        period,
    };
}

function to24Hour(hour12: string, minute: string, period: Period): string {
    const h = Number(hour12);
    let hour24 = h % 12;
    if (period === 'PM') {
        hour24 += 12;
    }

    return `${String(hour24).padStart(2, '0')}:${minute}`;
}

export function PushNotificationForm() {
    const { t } = useTranslation();
    const can = useCan();
    const form = useForm<PushNotificationFormValues>(emptyForm());
    const [scheduleEnabled, setScheduleEnabled] = useState(false);
    const [touched, setTouched] = useState<Record<keyof PushNotificationFormValues, boolean>>({
        title_en: false,
        title_zh: false,
        title_my: false,
        schedule_date: false,
        schedule_time: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof PushNotificationFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof PushNotificationFormValues>(field: K, value: PushNotificationFormValues[K]) => {
        form.setData(field, value);
        form.clearErrors(field);
    };

    const mode = scheduleEnabled ? 'schedule' : 'now';
    const timeParts = useMemo(() => splitTime12(form.data.schedule_time), [form.data.schedule_time]);

    const fieldState = (field: keyof PushNotificationFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validatePushNotificationField(field, form.data, t, mode) ? 'error' : 'success';
    };

    const fieldError = (field: keyof PushNotificationFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validatePushNotificationField(field, form.data, t, mode);
    };

    const resetForm = () => {
        form.setData(emptyForm());
        form.clearErrors();
        setTouched({
            title_en: false,
            title_zh: false,
            title_my: false,
            schedule_date: false,
            schedule_time: false,
        });
        setSubmitted(false);
        setScheduleEnabled(false);
    };

    const toggleSchedule = (checked: boolean) => {
        setScheduleEnabled(checked);

        if (!checked) {
            setField('schedule_date', '');
            setField('schedule_time', '');
            form.clearErrors('schedule_date', 'schedule_time');
            setTouched((current) => ({ ...current, schedule_date: false, schedule_time: false }));
        }
    };

    const writeTime = (next: Partial<TimeParts12>) => {
        const hour12 = next.hour12 ?? timeParts.hour12;
        const minute = next.minute ?? timeParts.minute;
        const period = next.period ?? timeParts.period;

        if (!hour12 || !minute) {
            if (next.hour12 === '' || next.minute === '') {
                setField('schedule_time', '');
            }
            return;
        }

        setField('schedule_time', to24Hour(hour12, minute, period));
        markTouched('schedule_time');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);
        setTouched({
            title_en: true,
            title_zh: true,
            title_my: true,
            schedule_date: scheduleEnabled,
            schedule_time: scheduleEnabled,
        });

        const errors = validatePushNotification(form.data, t, mode);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();

        const options = {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        };

        if (scheduleEnabled) {
            form.transform((data) => ({
                title_en: data.title_en,
                title_zh: data.title_zh,
                title_my: data.title_my,
                schedule_date: data.schedule_date,
                schedule_time: data.schedule_time,
            }));
            form.post('/notifications/compose/schedule', options);
            return;
        }

        form.transform((data) => ({
            title_en: data.title_en,
            title_zh: data.title_zh,
            title_my: data.title_my,
        }));
        form.post('/notifications/compose/push-now', options);
    };

    const processing = form.processing;
    const canCreate = can('notifications.create');
    const SubmitIcon = scheduleEnabled ? CalendarClockIcon : SendIcon;
    const submitLabel = scheduleEnabled ? t('notification.push.schedule_push') : t('notification.push.push_now');
    const timeState = fieldState('schedule_time');
    const scheduleDisabled = processing || !scheduleEnabled;
    const timeInvalid = timeState === 'error';

    return (
        <form
            onSubmit={submit}
            className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-stretch xl:grid-cols-[minmax(0,1fr)_320px]"
        >
            <section className="flex h-full flex-col rounded-[10px] border border-border/60 bg-background p-3.5 sm:p-4">
                <div className="mb-4 flex items-center gap-2">
                    <span className="flex size-6 items-center justify-center rounded-[6px] bg-primary/12 text-primary">
                        <LanguagesIcon className="size-3.5" strokeWidth={1.9} />
                    </span>
                    <p className="text-[12px] font-semibold text-foreground">{t('notification.push.title')}</p>
                </div>

                <div className="flex flex-1 flex-col gap-3.5">
                    {TITLE_FIELDS.map(({ field, lang, badge }) => (
                        <FormField
                            key={field}
                            label={
                                <span className="inline-flex items-center gap-2">
                                    <span className="inline-flex h-5 min-w-7 items-center justify-center rounded-[5px] bg-muted px-1.5 text-[10px] font-bold tracking-wide text-muted-foreground">
                                        {badge}
                                    </span>
                                    <span>{t(`language.${lang}`)}</span>
                                </span>
                            }
                            htmlFor={`push_${field}`}
                            error={fieldError(field)}
                            required
                            className="[&_[aria-live=polite]]:mt-2"
                        >
                            <Input
                                id={`push_${field}`}
                                value={form.data[field]}
                                maxLength={PUSH_TITLE_MAX_LENGTH}
                                placeholder={t(`notification.push.${field}`)}
                                aria-invalid={fieldState(field) === 'error'}
                                className={formControlStateClass(fieldState(field))}
                                onBlur={() => markTouched(field)}
                                onChange={(event) => setField(field, event.target.value)}
                                disabled={processing}
                            />
                        </FormField>
                    ))}
                </div>
            </section>

            <aside
                className={cn(
                    'flex flex-col rounded-[10px] border border-border/60 bg-muted/15 p-3.5 sm:p-4',
                    scheduleEnabled ? 'h-full gap-3.5' : 'self-start',
                )}
            >
                <div className="flex items-center justify-between gap-3">
                    <p className="text-[12px] font-semibold text-foreground">{t('notification.push.schedule_push')}</p>

                    <button
                        id="push_schedule_enabled"
                        type="button"
                        role="switch"
                        aria-checked={scheduleEnabled}
                        aria-label={t('notification.push.schedule_push')}
                        disabled={processing}
                        onClick={() => toggleSchedule(!scheduleEnabled)}
                        className={cn(
                            'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border transition-colors duration-200',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:ring-offset-1',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                            scheduleEnabled ? 'border-primary bg-primary' : 'border-border bg-background',
                        )}
                    >
                        <span
                            className={cn(
                                'inline-block size-3.5 rounded-full bg-white shadow-sm transition-transform duration-200',
                                scheduleEnabled ? 'translate-x-[17px]' : 'translate-x-0.5',
                            )}
                        />
                    </button>
                </div>

                <div
                    className={cn(
                        'grid transition-[grid-template-rows,opacity] duration-200 ease-out',
                        scheduleEnabled ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
                    )}
                    aria-hidden={!scheduleEnabled}
                >
                    <div className="min-h-0 overflow-hidden">
                        <div className="flex flex-col gap-3.5 border-t border-border/60 pt-3.5">
                            <FormField
                                label={t('notification.push.schedule_date')}
                                htmlFor="push_schedule_date"
                                error={fieldError('schedule_date')}
                                required
                                icon={CalendarIcon}
                                className="[&_[aria-live=polite]]:mt-2"
                            >
                                <DatePicker
                                    id="push_schedule_date"
                                    value={form.data.schedule_date}
                                    min={new Date().toISOString().slice(0, 10)}
                                    aria-invalid={fieldState('schedule_date') === 'error'}
                                    onBlur={() => markTouched('schedule_date')}
                                    onChange={(value) => {
                                        setField('schedule_date', value);
                                        markTouched('schedule_date');
                                    }}
                                    disabled={scheduleDisabled}
                                />
                            </FormField>

                            <FormField
                                label={t('notification.push.schedule_time')}
                                htmlFor="push_schedule_hour"
                                error={fieldError('schedule_time')}
                                required
                                className="[&_[aria-live=polite]]:mt-2"
                            >
                                <div
                                    className={cn(
                                        'rounded-[8px] border bg-surface p-2.5 transition-colors',
                                        timeState === 'error'
                                            ? 'border-danger'
                                            : timeState === 'success'
                                              ? 'border-success'
                                              : 'border-input',
                                    )}
                                >
                                    <div className="flex items-end justify-center gap-1.5">
                                        <div className="w-[72px] shrink-0">
                                            <label
                                                htmlFor="push_schedule_hour"
                                                className="mb-1 flex items-center gap-1 text-[10px] font-semibold text-muted-foreground"
                                            >
                                                <ClockIcon className="size-2.5" strokeWidth={2} />
                                                {t('notification.push.hour')}
                                            </label>
                                            <Select
                                                value={timeParts.hour12 || undefined}
                                                disabled={scheduleDisabled}
                                                onValueChange={(value) => {
                                                    writeTime({
                                                        hour12: value,
                                                        minute: timeParts.minute || '00',
                                                    });
                                                    markTouched('schedule_time');
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="push_schedule_hour"
                                                    aria-invalid={timeInvalid}
                                                    className="h-9 px-2 text-[13px] font-semibold tabular-nums"
                                                >
                                                    <SelectValue placeholder="--" />
                                                </SelectTrigger>
                                                <SelectContent className="min-w-20">
                                                    {HOURS_12.map((hour) => (
                                                        <SelectItem key={hour} value={hour} className="font-semibold tabular-nums">
                                                            {hour}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <span
                                            className="mb-2 text-[16px] font-bold leading-none text-muted-foreground"
                                            aria-hidden
                                        >
                                            :
                                        </span>

                                        <div className="w-[72px] shrink-0">
                                            <label
                                                htmlFor="push_schedule_minute"
                                                className="mb-1 block text-[10px] font-semibold text-muted-foreground"
                                            >
                                                {t('notification.push.minute')}
                                            </label>
                                            <Select
                                                value={timeParts.minute || undefined}
                                                disabled={scheduleDisabled}
                                                onValueChange={(value) => {
                                                    writeTime({
                                                        minute: value,
                                                        hour12: timeParts.hour12 || '12',
                                                    });
                                                    markTouched('schedule_time');
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="push_schedule_minute"
                                                    aria-invalid={timeInvalid}
                                                    className="h-9 px-2 text-[13px] font-semibold tabular-nums"
                                                >
                                                    <SelectValue placeholder="--" />
                                                </SelectTrigger>
                                                <SelectContent className="min-w-20">
                                                    {MINUTES.map((minute) => (
                                                        <SelectItem key={minute} value={minute} className="font-semibold tabular-nums">
                                                            {minute}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div
                                            className="mb-0 ml-1 grid w-[88px] shrink-0 grid-cols-2 gap-0.5 rounded-[7px] bg-muted/55 p-0.5"
                                            role="group"
                                            aria-label={t('notification.push.schedule_time')}
                                        >
                                            {(['AM', 'PM'] as const).map((period) => {
                                                const active = Boolean(form.data.schedule_time) && timeParts.period === period;

                                                return (
                                                    <button
                                                        key={period}
                                                        type="button"
                                                        disabled={scheduleDisabled}
                                                        aria-pressed={active}
                                                        onClick={() =>
                                                            writeTime({
                                                                period,
                                                                hour12: timeParts.hour12 || '12',
                                                                minute: timeParts.minute || '00',
                                                            })
                                                        }
                                                        className={cn(
                                                            'h-8 rounded-[5px] text-[10px] font-bold tracking-wide transition-all',
                                                            'disabled:pointer-events-none disabled:opacity-50',
                                                            active
                                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                                : 'text-muted-foreground hover:bg-background hover:text-foreground',
                                                        )}
                                                    >
                                                        {t(`notification.push.${period.toLowerCase()}`)}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </FormField>
                        </div>
                    </div>
                </div>

                {canCreate ? (
                    <Button
                        type="submit"
                        size="md"
                        variant="primary"
                        disabled={processing}
                        className={cn('w-full', scheduleEnabled ? 'mt-auto' : 'mt-3')}
                    >
                        {processing ? (
                            <Spinner size="xs" className="text-current" />
                        ) : (
                            <SubmitIcon className="size-4" strokeWidth={1.85} />
                        )}
                        {submitLabel}
                    </Button>
                ) : null}
            </aside>
        </form>
    );
}
