import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { FileTextIcon, CalendarClockIcon, CalendarIcon, CircleDotIcon, TypeIcon, Link2Icon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import {
    PROMOTION_TITLE_MAX_LENGTH,
    promotionSuccessMessage,
    validatePromotion,
    validatePromotionField,
} from '@/lib/promotion-validation';
import { cn } from '@/lib/utils';

export type PromotionFormValues = {
    title_en: string;
    title_my: string;
    title_zh: string;
    description_en: string;
    description_my: string;
    description_zh: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    slug: string;
    image: File | null;
};

type PromotionFormProps = {
    form: InertiaFormProps<PromotionFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

type TouchedFields = Record<keyof PromotionFormValues, boolean>;

const untouched: TouchedFields = {
    title_en: false,
    title_my: false,
    title_zh: false,
    description_en: false,
    description_my: false,
    description_zh: false,
    start_date: false,
    end_date: false,
    is_active: false,
    slug: false,
    image: false,
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function PromotionForm({ form, onSubmit, onCancel, mode = 'create', imageUrl }: PromotionFormProps) {
    const { t } = useTranslation();

    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');
    const [submitted, setSubmitted] = useState(false);
    const [dashedImage, setDashedImage] = useState<File | null>(form.data.image);

    const markTouched = (field: keyof PromotionFormValues) => {
        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const setField = <K extends keyof PromotionFormValues>(field: K, value: PromotionFormValues[K]) => {
        form.setData(field, value as never);

        // Clear Laravel error immediately when user changes the field.
        form.clearErrors(field);

        // If start_date changes, end_date validation may change too.
        if (field === 'start_date' && touched.end_date) {
            form.clearErrors('end_date');
        }

        // If title_en changes, slug changes too.
        if (field === 'title_en') {
            form.clearErrors('slug');
        }
    };

    const fieldState = (field: keyof PromotionFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        const serverError = form.errors[field];
        const clientError = validatePromotionField(field, form.data, t);

        if (serverError || clientError) {
            return 'error';
        }

        return 'success';
    };

    const fieldError = (field: keyof PromotionFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validatePromotionField(field, form.data, t);
    };

    const fieldSuccess = (field: keyof PromotionFormValues): string | undefined => {
        return fieldState(field) === 'success' ? promotionSuccessMessage(field, t) : undefined;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);

        setTouched({
            title_en: true,
            title_my: true,
            title_zh: true,
            description_en: true,
            description_my: true,
            description_zh: true,
            start_date: true,
            end_date: true,
            is_active: true,
            slug: true,
            image: true,
        });

        const errors = validatePromotion(form.data, t);

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
                    label={t('cms.news.title_en')}
                    htmlFor="title_en"
                    error={fieldError('title_en')}
                    success={fieldSuccess('title_en')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_en"
                        value={form.data.title_en}
                        maxLength={PROMOTION_TITLE_MAX_LENGTH}
                        aria-invalid={fieldState('title_en') === 'error'}
                        className={formControlStateClass(fieldState('title_en'))}
                        onBlur={() => markTouched('title_en')}
                        onChange={(event) => {
                            setField('title_en', event.target.value);
                            if (!slugTouched) {
                                setField('slug', toSlug(event.target.value));
                            }
                        }}
                    />
                </FormField>
                <FormField
                    label={t('cms.news.description_en')}
                    htmlFor="description_en"
                    error={fieldError('description_en')}
                    success={fieldSuccess('description_en')}
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_en"
                        className={cn('min-h-40', formControlStateClass(fieldState('description_en')))}
                        value={form.data.description_en}
                        aria-invalid={fieldState('description_en') === 'error'}
                        onBlur={() => markTouched('description_en')}
                        onChange={(event) => setField('description_en', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('cms.news.title_zh')}
                    htmlFor="title_zh"
                    error={fieldError('title_zh')}
                    success={fieldSuccess('title_zh')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_zh"
                        value={form.data.title_zh}
                        maxLength={PROMOTION_TITLE_MAX_LENGTH}
                        aria-invalid={fieldState('title_zh') === 'error'}
                        className={formControlStateClass(fieldState('title_zh'))}
                        onBlur={() => markTouched('title_zh')}
                        onChange={(event) => setField('title_zh', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('cms.news.description_zh')}
                    htmlFor="description_zh"
                    error={fieldError('description_zh')}
                    success={fieldSuccess('description_zh')}
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_zh"
                        className={cn('min-h-40', formControlStateClass(fieldState('description_zh')))}
                        value={form.data.description_zh}
                        aria-invalid={fieldState('description_zh') === 'error'}
                        onBlur={() => markTouched('description_zh')}
                        onChange={(event) => setField('description_zh', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('cms.slug')}
                    htmlFor="slug"
                    error={fieldError('slug')}
                    success={fieldSuccess('slug')}
                    icon={Link2Icon}
                    className="mb-3"
                    required
                >
                    <Input
                        id="slug"
                        value={form.data.slug ?? ''}
                        aria-invalid={fieldState('slug') === 'error'}
                        className={formControlStateClass(fieldState('slug'))}
                        onBlur={() => markTouched('slug')}
                        onChange={(event) => setField('slug', event.target.value)}
                    />
                </FormField>
            </div>
            <div className="ml-3">
                <FormField
                    label={t('cms.image')}
                    htmlFor="image"
                    error={fieldError('image')}
                    success={fieldSuccess('image')}
                    className="mb-3"
                >
                    <SquareImageUpload
                        id="image"
                        width={620}
                        height={240}
                        value={dashedImage}
                        existingUrl={imageUrl}
                        onChange={(file) => {
                            setDashedImage(file);
                            setField('image', file);
                            markTouched('image');
                        }}
                    />
                </FormField>
                <FormField
                    label={t('cms.news.title_my')}
                    htmlFor="title_my"
                    error={fieldError('title_my')}
                    success={fieldSuccess('title_my')}
                    required
                    icon={TypeIcon}
                    className="mb-3"
                >
                    <Input
                        id="title_my"
                        value={form.data.title_my}
                        maxLength={PROMOTION_TITLE_MAX_LENGTH}
                        aria-invalid={fieldState('title_my') === 'error'}
                        className={formControlStateClass(fieldState('title_my'))}
                        onBlur={() => markTouched('title_my')}
                        onChange={(event) => setField('title_my', event.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('cms.news.description_my')}
                    htmlFor="description_my"
                    error={fieldError('description_my')}
                    success={fieldSuccess('description_my')}
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_my"
                        className={cn('min-h-40', formControlStateClass(fieldState('description_my')))}
                        value={form.data.description_my}
                        aria-invalid={fieldState('description_my') === 'error'}
                        onBlur={() => markTouched('description_my')}
                        onChange={(event) => setField('description_my', event.target.value)}
                    />
                </FormField>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <FormField
                        label={t('common.status')}
                        htmlFor="is_active"
                        error={fieldError('is_active')}
                        success={fieldSuccess('is_active')}
                        icon={CircleDotIcon}
                        className="mb-3"
                        required
                    >
                        <Select
                            value={form.data.is_active ? '1' : '0'}
                            onValueChange={(value) => {
                                setField('is_active', value === '1');
                                markTouched('is_active');
                            }}
                        >
                            <SelectTrigger
                                id="is_active"
                                className={formControlStateClass(fieldState('is_active'))}
                                aria-invalid={fieldState('is_active') === 'error'}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="1">{t('status.active')}</SelectItem>
                                <SelectItem value="0">{t('status.inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={t('cms.start_date')}
                        htmlFor="start_date"
                        error={fieldError('start_date')}
                        success={fieldSuccess('start_date')}
                        icon={CalendarIcon}
                        className="mb-3"
                    >
                        <DatePicker
                            id="start_date"
                            value={form.data.start_date ?? ''}
                            max={form.data.end_date || undefined}
                            disabled={!form.data.is_active}
                            aria-invalid={fieldState('start_date') === 'error'}
                            className={formControlStateClass(fieldState('start_date'))}
                            onBlur={() => markTouched('start_date')}
                            onChange={(value) => setField('start_date', value)}
                        />
                    </FormField>
                    <FormField
                        label={t('cms.end_date')}
                        htmlFor="end_date"
                        error={fieldError('end_date')}
                        success={fieldSuccess('end_date')}
                        icon={CalendarClockIcon}
                        className="mb-3"
                    >
                        <DatePicker
                            id="end_date"
                            value={form.data.end_date ?? ''}
                            min={form.data.start_date || undefined}
                            disabled={!form.data.is_active}
                            aria-invalid={fieldState('end_date') === 'error'}
                            className={formControlStateClass(fieldState('end_date'))}
                            onBlur={() => markTouched('end_date')}
                            onChange={(value) => setField('end_date', value)}
                        />
                    </FormField>
                </div>
            </div>
        </CmsFormShell>
    );
}
