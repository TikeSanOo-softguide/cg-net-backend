import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CalendarClockIcon, CheckIcon, CircleDotIcon, CalendarIcon, FileTextIcon, MegaphoneIcon, SettingsIcon, TypeIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { useTranslation } from '@/hooks/useTranslation';
import { ANNOUNCEMENT_TYPES, validateAnnouncement, validateAnnouncementField, type AnnouncementType } from '@/lib/announcement-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';
import { DateTimePicker } from '@/components/ui/date-time-picker';

export type AnnouncementFormValues = {
    type: AnnouncementType;
    title_en: string;
    title_zh: string;
    title_my: string;
    content_en: string;
    content_zh: string;
    content_my: string;
    start_date: string | undefined;
    end_date: string | undefined;
    is_active: boolean;
};

type AnnouncementFormProps = {
    form: InertiaFormProps<AnnouncementFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
};

export function AnnouncementForm({ form, onSubmit, onCancel, mode = 'create' }: AnnouncementFormProps) {
    const { t } = useTranslation();
    const [touched, setTouched] = useState<Record<keyof AnnouncementFormValues, boolean>>({
        type: false,
        title_en: false,
        title_zh: false,
        title_my: false,
        content_en: false,
        content_zh: false,
        content_my: false,
        start_date: false,
        end_date: false,
        is_active: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof AnnouncementFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof AnnouncementFormValues>(field: K, value: AnnouncementFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof AnnouncementFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validateAnnouncementField(field, form.data, t) ? 'error' : 'success';
    };

    const fieldError = (field: keyof AnnouncementFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateAnnouncementField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            type: true,
            title_en: true,
            title_zh: true,
            title_my: true,
            content_en: true,
            content_zh: true,
            content_my: true,
            start_date: true,
            end_date: true,
            is_active: true,
        });

        const errors = validateAnnouncement(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);

            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    return (
        <CmsFormShell onSubmit={submit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <div className="sm:col-span-2">
                <p className="mb-2 text-[12px] font-medium text-foreground">{t('notification.announcement.type')}</p>
                <div className="grid grid-cols-2 gap-2">
                    {ANNOUNCEMENT_TYPES.map((type) => {
                        const selected = form.data.type === type;
                        const Icon = type === 'system' ? SettingsIcon : MegaphoneIcon;

                        return (
                            <button
                                key={type}
                                type="button"
                                aria-pressed={selected}
                                onClick={() => {
                                    setField('type', type);
                                    markTouched('type');
                                }}
                                className={cn(
                                    'group flex items-center gap-2 rounded-[6px] border px-2.5 py-2 text-left transition-colors',
                                    selected
                                        ? 'border-primary bg-primary/10 ring-1 ring-primary/25'
                                        : 'border-border/70 bg-background hover:border-primary/30',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-[6px] transition-colors',
                                        selected
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground group-hover:text-primary',
                                    )}
                                >
                                    <Icon className="size-3.5" strokeWidth={1.8} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className={cn('block truncate text-[12px] font-semibold leading-4', selected ? 'text-primary' : 'text-foreground')}>
                                        {t(`notification.announcement.types.${type}`)}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'flex size-4 shrink-0 items-center justify-center rounded-full border',
                                        selected
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background text-transparent',
                                    )}
                                >
                                    <CheckIcon className="size-2.5" strokeWidth={3} />
                                </span>
                            </button>
                        );
                    })}
                </div>
                {fieldError('type') ? <p className="mt-1.5 text-[12px] text-danger">{fieldError('type')}</p> : null}
            </div>
            <div>
                <FormField
                    label={t('notification.announcement.title_en')}
                    htmlFor="title_en"
                    error={fieldError('title_en')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_en"
                        value={form.data.title_en}
                        aria-invalid={fieldState('title_en') === 'error'}
                        className={formControlStateClass(fieldState('title_en'))}
                        onBlur={() => markTouched('title_en')}
                        onChange={(event) => setField('title_en', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('notification.announcement.content_en')}
                    htmlFor="content_en"
                    error={fieldError('content_en')}
                    required
                    icon={FileTextIcon}
                >
                    <Textarea
                        id="content_en"
                        className={cn('h-36', formControlStateClass(fieldState('content_en')))}
                        value={form.data.content_en}
                        rows={5}
                        aria-invalid={fieldState('content_en') === 'error'}
                        onBlur={() => markTouched('content_en')}
                        onChange={(event) => setField('content_en', event.target.value)}
                    />
                </FormField>
            </div>
            <div className="md:ml-3">
                <FormField
                    label={t('notification.announcement.title_zh')}
                    htmlFor="title_zh"
                    error={fieldError('title_zh')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_zh"
                        value={form.data.title_zh}
                        aria-invalid={fieldState('title_zh') === 'error'}
                        className={formControlStateClass(fieldState('title_zh'))}
                        onBlur={() => markTouched('title_zh')}
                        onChange={(event) => setField('title_zh', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('notification.announcement.content_zh')}
                    htmlFor="content_zh"
                    error={fieldError('content_zh')}
                    required
                    icon={FileTextIcon}
                >
                    <Textarea
                        id="content_zh"
                        className={cn('h-36', formControlStateClass(fieldState('content_zh')))}
                        value={form.data.content_zh}
                        rows={5}
                        aria-invalid={fieldState('content_zh') === 'error'}
                        onBlur={() => markTouched('content_zh')}
                        onChange={(event) => setField('content_zh', event.target.value)}
                    />
                </FormField>
            </div>
            <div>
                <FormField
                    label={t('notification.announcement.title_my')}
                    htmlFor="title_my"
                    error={fieldError('title_my')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_my"
                        value={form.data.title_my}
                        aria-invalid={fieldState('title_my') === 'error'}
                        className={formControlStateClass(fieldState('title_my'))}
                        onBlur={() => markTouched('title_my')}
                        onChange={(event) => setField('title_my', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('notification.announcement.content_my')}
                    htmlFor="content_my"
                    error={fieldError('content_my')}
                    required
                    icon={FileTextIcon}
                >
                    <Textarea
                        id="content_my"
                        className={cn('h-36', formControlStateClass(fieldState('content_my')))}
                        value={form.data.content_my}
                        rows={5}
                        aria-invalid={fieldState('content_my') === 'error'}
                        onBlur={() => markTouched('content_my')}
                        onChange={(event) => setField('content_my', event.target.value)}
                    />
                </FormField>
            </div>
            <div className="md:ml-3">
                <FormField
                    label={t('common.status')}
                    htmlFor="is_active"
                    error={form.errors.is_active}
                    className="mb-5"
                >
                    <StaffStatusSwitch
                        id="is_active"
                        value={form.data.is_active ? 'active' : 'inactive'}
                        onChange={(value) => setField('is_active', value === 'active')}
                    />
                </FormField>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField
                        label={t('common.start_date_time')}
                        htmlFor="start_date"
                        error={form.errors.start_date}
                        icon={CalendarClockIcon}
                    >
                        <DateTimePicker
                            id="start_date"
                            name="start_date"
                            value={form.data.start_date}
                            onChange={(value) => form.setData('start_date', value)}
                            clearable
                        />
                    </FormField>
                    <FormField
                        label={t('common.end_date_time')}
                        htmlFor="end_date"
                        error={form.errors.end_date}
                        icon={CalendarClockIcon}
                    >
                        <DateTimePicker
                            id="end_date"
                            name="end_date"
                            value={form.data.end_date}
                            onChange={(value) => form.setData('end_date', value)}
                            clearable
                        />
                    </FormField>
                </div>
            </div>
        </CmsFormShell>
    );
}
