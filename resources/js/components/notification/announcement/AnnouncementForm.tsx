import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CalendarClockIcon, CircleDotIcon, CalendarIcon, FileTextIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { useTranslation } from '@/hooks/useTranslation';
import {
    announcementSuccessMessage,
    validateAnnouncement,
    validateAnnouncementField,
} from '@/lib/announcement-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';
import { DateTimePicker } from '@/components/ui/date-time-picker';

export type AnnouncementFormValues = {
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
            <div>
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
                        aria-invalid={fieldState('content_zh') === 'error'}
                        onBlur={() => markTouched('content_zh')}
                        onChange={(event) => setField('content_zh', event.target.value)}
                    />
                </FormField>
            </div>
            <div>
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
