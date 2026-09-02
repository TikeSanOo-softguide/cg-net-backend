import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CalendarClockIcon, CalendarIcon, CircleDotIcon, HashIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { validateBanner, validateBannerField } from '@/lib/banner-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type BannerFormValues = {
    image_url_en: File | null;
    image_url_zh: File | null;
    image_url_my: File | null;
    sort_order: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

type BannerFormProps = {
    form: InertiaFormProps<BannerFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
    imageUrls?: {
        en?: string | null;
        zh?: string | null;
        my?: string | null;
    };
};

type ImageField = 'image_url_en' | 'image_url_zh' | 'image_url_my';

export function BannerForm({ form, onSubmit, onCancel, mode = 'create', imageUrls }: BannerFormProps) {
    const { t } = useTranslation();

    const [imageEn, setImageEn] = useState<File | null>(null);
    const [imageZh, setImageZh] = useState<File | null>(null);
    const [imageMy, setImageMy] = useState<File | null>(null);

    const [submitted, setSubmitted] = useState(false);

    const [touched, setTouched] = useState<Record<keyof BannerFormValues, boolean>>({
        image_url_en: false,
        image_url_zh: false,
        image_url_my: false,
        sort_order: false,
        start_date: false,
        end_date: false,
        is_active: false,
    });

    const existingImages: Partial<Record<ImageField, boolean>> = {
        image_url_en: Boolean(imageUrls?.en),
        image_url_zh: Boolean(imageUrls?.zh),
        image_url_my: Boolean(imageUrls?.my),
    };

    const markTouched = (field: keyof BannerFormValues) => {
        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const setField = <K extends keyof BannerFormValues>(field: K, value: BannerFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const hasExistingImage = (field: keyof BannerFormValues): boolean => {
        if (field === 'image_url_en') {
            return existingImages.image_url_en ?? false;
        }

        if (field === 'image_url_zh') {
            return existingImages.image_url_zh ?? false;
        }

        if (field === 'image_url_my') {
            return existingImages.image_url_my ?? false;
        }

        return false;
    };

    const fieldError = (field: keyof BannerFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateBannerField(field, form.data, t, mode, hasExistingImage(field));
    };

    const fieldState = (field: keyof BannerFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        const error = form.errors[field] || validateBannerField(field, form.data, t, mode, hasExistingImage(field));

        return error ? 'error' : 'success';
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);

        setTouched({
            image_url_en: true,
            image_url_zh: true,
            image_url_my: true,
            sort_order: true,
            start_date: true,
            end_date: true,
            is_active: true,
        });

        const errors = validateBanner(form.data, t, mode, existingImages);
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
                    label={t('cms.banner.image_en')}
                    htmlFor="banner-image-en"
                    required
                    className="mb-3"
                    error={fieldError('image_url_en')}
                >
                    <SquareImageUpload
                        id="banner-image-en"
                        width={600}
                        height={200}
                        value={imageEn}
                        existingUrl={imageUrls?.en}
                        onChange={(file) => {
                            markTouched('image_url_en');
                            setImageEn(file);
                            setField('image_url_en', file);
                        }}
                    />
                </FormField>

                <FormField
                    label={t('cms.banner.image_zh')}
                    htmlFor="banner-image-zh"
                    required
                    className="mb-3"
                    error={fieldError('image_url_zh')}
                >
                    <SquareImageUpload
                        id="banner-image-zh"
                        width={600}
                        height={200}
                        value={imageZh}
                        existingUrl={imageUrls?.zh}
                        onChange={(file) => {
                            markTouched('image_url_zh');
                            setImageZh(file);
                            setField('image_url_zh', file);
                        }}
                    />
                </FormField>
            </div>

            <div className="ml-3">
                <FormField
                    label={t('cms.banner.image_my')}
                    htmlFor="banner-image-my"
                    required
                    className="mb-3"
                    error={fieldError('image_url_my')}
                >
                    <SquareImageUpload
                        id="banner-image-my"
                        width={600}
                        height={200}
                        value={imageMy}
                        existingUrl={imageUrls?.my}
                        onChange={(file) => {
                            markTouched('image_url_my');
                            setImageMy(file);
                            setField('image_url_my', file);
                        }}
                    />
                </FormField>

                <div className="mb-3 grid grid-cols-2 gap-3">
                    <FormField
                        label={t('cms.sort_order')}
                        htmlFor="sort_order"
                        error={fieldError('sort_order')}
                        icon={HashIcon}
                    >
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            className={cn('w-full', formControlStateClass(fieldState('sort_order')))}
                            onChange={(event) => {
                                markTouched('sort_order');

                                setField('sort_order', Number(event.target.value));
                            }}
                        />
                    </FormField>

                    <FormField
                        label={t('common.status')}
                        htmlFor="is_active"
                        error={fieldError('is_active')}
                        icon={CircleDotIcon}
                    >
                        <Select
                            value={form.data.is_active ? '1' : '0'}
                            onValueChange={(value) => {
                                markTouched('is_active');

                                setField('is_active', value === '1');
                            }}
                        >
                            <SelectTrigger
                                id="is_active"
                                className={cn('w-full', formControlStateClass(fieldState('is_active')))}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="1">{t('status.active')}</SelectItem>

                                <SelectItem value="0">{t('status.inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <FormField
                        label={t('cms.start_date')}
                        htmlFor="start_date"
                        error={fieldError('start_date')}
                        icon={CalendarIcon}
                    >
                        <DatePicker
                            id="start_date"
                            value={form.data.start_date}
                            max={form.data.end_date || undefined}
                            className={cn('w-full', formControlStateClass(fieldState('start_date')))}
                            onChange={(value) => {
                                markTouched('start_date');

                                setField('start_date', value);
                            }}
                        />
                    </FormField>

                    <FormField
                        label={t('cms.end_date')}
                        htmlFor="end_date"
                        error={fieldError('end_date')}
                        icon={CalendarClockIcon}
                    >
                        <DatePicker
                            id="end_date"
                            value={form.data.end_date}
                            min={form.data.start_date || undefined}
                            className={cn('w-full', formControlStateClass(fieldState('end_date')))}
                            onChange={(value) => {
                                markTouched('end_date');

                                setField('end_date', value);
                            }}
                        />
                    </FormField>
                </div>
            </div>
        </CmsFormShell>
    );
}
