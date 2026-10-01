import { FormEvent, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CalendarClockIcon, CalendarIcon, ClockIcon, LanguagesIcon, SendIcon, TypeIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
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

const TITLE_FIELDS = ['title_en', 'title_zh', 'title_my'] as const;
const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const MINUTES = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

function splitTime(value: string): { hour: string; minute: string } {
    const match = value.match(/^(\d{2}):(\d{2})$/);
    if (!match) {
        return { hour: '', minute: '' };
    }

    return { hour: match[1], minute: match[2] };
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
    const timeParts = useMemo(() => splitTime(form.data.schedule_time), [form.data.schedule_time]);
    const minuteOptions = useMemo(
        () => [...new Set([...MINUTES, ...(timeParts.minute ? [timeParts.minute] : [])])].sort(),
        [timeParts.minute],
    );

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

    const setTimePart = (part: 'hour' | 'minute', value: string) => {
        if (!value) {
            setField('schedule_time', '');
            return;
        }

        const hour = part === 'hour' ? value : timeParts.hour || '00';
        const minute = part === 'minute' ? value : timeParts.minute || '00';

        setField('schedule_time', `${hour}:${minute}`);
        markTouched('schedule_time');
    };

    const applyQuickTime = (minutesFromNow: number) => {
        const date = new Date(Date.now() + minutesFromNow * 60_000);
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        const hour = String(date.getHours()).padStart(2, '0');
        const minute = String(Math.floor(date.getMinutes() / 5) * 5).padStart(2, '0');

        setField('schedule_date', `${y}-${m}-${d}`);
        setField('schedule_time', `${hour}:${minute}`);
        markTouched('schedule_date');
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

    const selectClass =
        'h-8 min-w-0 flex-1 rounded-[4px] border-0 bg-transparent px-1 text-center text-[13px] font-semibold tabular-nums outline-none disabled:cursor-not-allowed disabled:opacity-70';

    return (
        <form
            onSubmit={submit}
            className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start xl:grid-cols-[minmax(0,1fr)_320px]"
        >
            <section className="rounded-[10px] border border-border/60 bg-background p-3.5 sm:p-4">
                <div className="mb-3 flex items-center gap-2">
                    <span className="flex size-6 items-center justify-center rounded-[6px] bg-primary/12 text-primary">
                        <LanguagesIcon className="size-3.5" strokeWidth={1.9} />
                    </span>
                    <p className="text-[12px] font-semibold text-foreground">{t('notification.push.title')}</p>
                </div>

                <div className="flex flex-col gap-1">
                    {TITLE_FIELDS.map((field) => (
                        <FormField
                            key={field}
                            label={t(`notification.push.${field}`)}
                            htmlFor={`push_${field}`}
                            error={fieldError(field)}
                            required
                            icon={TypeIcon}
                        >
                            <Input
                                id={`push_${field}`}
                                value={form.data[field]}
                                maxLength={PUSH_TITLE_MAX_LENGTH}
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

            <aside className="flex flex-col gap-3 rounded-[10px] border border-border/60 bg-muted/15 p-3.5 sm:p-4">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[12px] font-semibold text-foreground">{t('notification.push.schedule_push')}</p>
                        <p className="text-[11px] leading-4 text-muted-foreground">
                            {t('notification.push.schedule_checkbox_hint')}
                        </p>
                    </div>

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
                        <div className="flex flex-col gap-1 border-t border-border/60 pt-3">
                            <FormField
                                label={t('notification.push.schedule_date')}
                                htmlFor="push_schedule_date"
                                error={fieldError('schedule_date')}
                                required
                                icon={CalendarIcon}
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
                            >
                                <div
                                    className={cn(
                                        'flex h-10 items-center gap-1 rounded-[6px] border bg-surface px-2 transition-colors',
                                        timeState === 'error'
                                            ? 'border-danger'
                                            : timeState === 'success'
                                              ? 'border-success'
                                              : 'border-input hover:border-primary/35 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/40',
                                    )}
                                >
                                    <ClockIcon className="size-4 shrink-0 text-muted-foreground" strokeWidth={1.75} />
                                    <select
                                        id="push_schedule_hour"
                                        value={timeParts.hour}
                                        disabled={scheduleDisabled}
                                        aria-label={t('notification.push.hour')}
                                        onBlur={() => markTouched('schedule_time')}
                                        onChange={(event) => setTimePart('hour', event.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">{t('notification.push.hour')}</option>
                                        {HOURS.map((hour) => (
                                            <option key={hour} value={hour}>
                                                {hour}
                                            </option>
                                        ))}
                                    </select>
                                    <span className="text-[13px] font-bold text-muted-foreground" aria-hidden>
                                        :
                                    </span>
                                    <select
                                        id="push_schedule_minute"
                                        value={timeParts.minute}
                                        disabled={scheduleDisabled}
                                        aria-label={t('notification.push.minute')}
                                        onBlur={() => markTouched('schedule_time')}
                                        onChange={(event) => setTimePart('minute', event.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">{t('notification.push.minute')}</option>
                                        {minuteOptions.map((minute) => (
                                            <option key={minute} value={minute}>
                                                {minute}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </FormField>

                            <div className="-mt-1 flex flex-wrap gap-1.5">
                                {[
                                    { label: t('notification.push.quick_15m'), minutes: 15 },
                                    { label: t('notification.push.quick_1h'), minutes: 60 },
                                    { label: t('notification.push.quick_3h'), minutes: 180 },
                                ].map((item) => (
                                    <button
                                        key={item.minutes}
                                        type="button"
                                        disabled={scheduleDisabled}
                                        onClick={() => applyQuickTime(item.minutes)}
                                        className="rounded-full border border-border/70 bg-background px-2.5 py-1 text-[10px] font-semibold text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/8 hover:text-primary disabled:pointer-events-none disabled:opacity-50"
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {canCreate ? (
                    <Button type="submit" size="md" variant="primary" disabled={processing} className="w-full">
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
