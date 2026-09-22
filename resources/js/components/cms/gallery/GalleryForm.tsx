import { FormEvent, useState, useEffect } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { TagIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { validateGallery, validateGalleryField } from '@/lib/gallery-validation';

export type GalleryFormValues = {
    label_en: string | null;
    label_my: string | null;
    label_zh: string | null;
    image: File | null;
};

type GalleryFormProps = {
    form: InertiaFormProps<GalleryFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

type TouchedFields = Record<keyof GalleryFormValues, boolean>;

const untouched: TouchedFields = {
    label_en: false,
    label_my: false,
    label_zh: false,
    image: false,
};

export function GalleryForm({ form, onSubmit, onCancel, mode = 'create', imageUrl }: GalleryFormProps) {
    const { t } = useTranslation();

    const [dashedImage, setDashedImage] = useState<File | null>(null);
    const [existingImageUrl, setExistingImageUrl] = useState(imageUrl ?? null);
    const [touched, setTouched] = useState<TouchedFields>(untouched);
    const [submitted, setSubmitted] = useState(false);
    const [imageDimensions, setImageDimensions] = useState<{
        width: number;
        height: number;
    } | null>(null);

    useEffect(() => {
        if (!existingImageUrl) {
            setImageDimensions(null);
            return;
        }

        const image = new Image();

        image.onload = () => {
            setImageDimensions({
                width: image.naturalWidth,
                height: image.naturalHeight,
            });
        };
        image.src = existingImageUrl;
    }, [existingImageUrl]);

    const hasExistingImage = Boolean(existingImageUrl);

    const handleImageChange = (file: File | null) => {
        setDashedImage(file);
        setExistingImageUrl(null);
        form.setData('image', file);
        form.clearErrors('image');

        if (mode === 'edit' && hasExistingImage && !file) {
            return;
        }

        const error = validateGalleryField(
            'image',
            {
                ...form.data,
                image: file,
            },
            t,
            mode,
            hasExistingImage,
        );

        if (error) {
            form.setError('image', error);
        }
        if (!file) {
            setImageDimensions(null);
            return;
        }

        const image = new Image();

        image.onload = () => {
            setImageDimensions({
                width: image.naturalWidth,
                height: image.naturalHeight,
            });

            URL.revokeObjectURL(image.src);
        };

        image.src = URL.createObjectURL(file);
    };

    const markTouched = (field: keyof GalleryFormValues) => {
        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const setField = <K extends keyof GalleryFormValues>(field: K, value: GalleryFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof GalleryFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validateGalleryField(field, form.data, t, mode, hasExistingImage)
            ? 'error'
            : 'success';
    };

    const fieldError = (field: keyof GalleryFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateGalleryField(field, form.data, t, mode, hasExistingImage);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            label_en: true,
            label_my: true,
            label_zh: true,
            image: true,
        });

        const errors = validateGallery(form.data, t, mode, hasExistingImage);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();
        onSubmit(event);
    };

    return (
        <CmsFormShell onSubmit={submit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <FormField
                label={t('cms.gallery.label_en')}
                htmlFor="label_en"
                error={fieldError('label_en')}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label_en"
                    value={form.data.label_en ?? ''}
                    aria-invalid={fieldState('label_en') === 'error'}
                    className={formControlStateClass(fieldState('label_en'))}
                    onBlur={() => markTouched('label_en')}
                    onChange={(event) => setField('label_en', event.target.value)}
                />
            </FormField>

            <FormField
                label={t('cms.gallery.label_my')}
                htmlFor="label_my"
                error={fieldError('label_my')}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label_my"
                    value={form.data.label_my ?? ''}
                    aria-invalid={fieldState('label_my') === 'error'}
                    className={formControlStateClass(fieldState('label_my'))}
                    onBlur={() => markTouched('label_my')}
                    onChange={(event) => setField('label_my', event.target.value)}
                />
            </FormField>

            <FormField
                label={t('cms.gallery.label_zh')}
                htmlFor="label_zh"
                error={fieldError('label_zh')}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label_zh"
                    value={form.data.label_zh ?? ''}
                    aria-invalid={fieldState('label_zh') === 'error'}
                    className={formControlStateClass(fieldState('label_zh'))}
                    onBlur={() => markTouched('label_zh')}
                    onChange={(event) => setField('label_zh', event.target.value)}
                />
            </FormField>

            <FormField
                label={
                    <div className="flex w-full items-center justify-between border-b border-border/40 pb-3">
                        <div className="flex items-center gap-1">
                            <span>{t('cms.image')}</span>
                            <span>*</span>
                        </div>

                        <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                            {t('cms.news.image_size')}:{' '}
                            {imageDimensions
                                ? `${imageDimensions.width} × ${imageDimensions.height} px`
                                : '4032 × 3024  px'}
                        </span>
                    </div>
                }
                htmlFor="image"
                error={fieldError('image')}
                className="sm:col-span-2"
            >
                <SquareImageUpload
                    id="dashboard-image-dashed"
                    width={4032}
                    aspectRatio="4032 / 3024"
                    value={dashedImage}
                    existingUrl={existingImageUrl}
                    className={formControlStateClass(fieldState('image'))}
                    onChange={handleImageChange}
                />
            </FormField>
        </CmsFormShell>
    );
}
