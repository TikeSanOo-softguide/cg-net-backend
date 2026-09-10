import { FormEvent, useEffect, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    CalendarClockIcon,
    CalendarIcon,
    CheckIcon,
    CircleDotIcon,
    EyeIcon,
    HashIcon,
    ImageIcon,
    Settings2Icon,
    XIcon,
} from 'lucide-react';

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

export type BannerType = 'web_background' | 'web_popup' | 'app_entry' | 'app_popup';

const bannerTypes = [
    {
        value: 'web_background',
        labelKey: 'cms.banner.types.web_background',
        width: 1920,
        height: 150,
    },
    {
        value: 'web_popup',
        labelKey: 'cms.banner.types.web_popup',
        width: 800,
        height: 350,
    },
    {
        value: 'app_entry',
        labelKey: 'cms.banner.types.app_entry',
        width: 270,
        height: 360,
    },
    {
        value: 'app_popup',
        labelKey: 'cms.banner.types.app_popup',
        width: 200,
        height: 250,
    },
];
export type BannerFormValues = {
    type: BannerType;
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

interface PreviewModalState {
    url: string;
    title: string;
}

export function BannerForm({ form, onSubmit, onCancel, mode = 'create', imageUrls }: BannerFormProps) {
    const { t } = useTranslation();

    const [imageEn, setImageEn] = useState<File | null>(null);
    const [imageZh, setImageZh] = useState<File | null>(null);
    const [imageMy, setImageMy] = useState<File | null>(null);
    const [submitted, setSubmitted] = useState(false);
    const [activePreview, setActivePreview] = useState<PreviewModalState | null>(null);

    const [touched, setTouched] = useState<Record<keyof BannerFormValues, boolean>>({
        image_url_en: false,
        image_url_zh: false,
        image_url_my: false,
        type: false,
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

    const selectedBannerType = bannerTypes.find((b) => b.value === form.data.type) ?? bannerTypes[0];

    const getPreviewSize = () => {
        switch (selectedBannerType.value) {
            case 'web_background':
                return 'aspect-[1920/550] w-screen';
            case 'web_popup':
                return 'aspect-[800/350] w-[min(100vw,800px)]';
            case 'app_entry':
                return 'aspect-[270/360] h-[80vh] w-auto';
            case 'app_popup':
                return 'aspect-[200/250] h-[70vh] w-auto';
            default:
                return 'max-w-[100vw] max-h-[90vh]';
        }
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
        if (field === 'image_url_en') return existingImages.image_url_en ?? false;
        if (field === 'image_url_zh') return existingImages.image_url_zh ?? false;
        if (field === 'image_url_my') return existingImages.image_url_my ?? false;
        return false;
    };

    const fieldError = (field: keyof BannerFormValues): string | undefined => {
        if (!touched[field] && !submitted) return undefined;
        return form.errors[field] || validateBannerField(field, form.data, t, mode, hasExistingImage(field));
    };

    const fieldState = (field: keyof BannerFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) return 'idle';
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
            type: true,
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

    const handleOpenPreview = (file: File | null, existingUrl: string | null | undefined, labelKey: string) => {
        let previewUrl: string | null = null;

        if (file) {
            previewUrl = URL.createObjectURL(file);
        } else if (existingUrl) {
            previewUrl = existingUrl;
        }

        if (previewUrl) {
            setActivePreview({
                url: previewUrl,
                title: t(labelKey),
            });
        }
    };

    const renderImageField = (
        field: ImageField,
        labelKey: string,
        fileState: File | null,
        setFileState: (file: File | null) => void,
        existingUrl?: string | null,
    ) => {
        const hasImage = Boolean(fileState || existingUrl);

        return (
            <FormField label={t(labelKey)} htmlFor={`banner-${field}`} required error={fieldError(field)}>
                <div
                    className={cn(
                        'flex flex-col gap-2 rounded-xl border border-dashed border-border/80 bg-muted/20 p-3 transition-colors hover:border-primary/40',
                        ['app_popup', 'app_entry'].includes(selectedBannerType.value) && 'items-center',
                    )}
                >
                    <SquareImageUpload
                        id={`banner-${field}`}
                        width={selectedBannerType.width}
                        height={selectedBannerType.height}
                        value={fileState}
                        existingUrl={existingUrl}
                        onChange={(file) => {
                            markTouched(field);
                            setFileState(file);
                            setField(field, file);
                        }}
                    />
                    {hasImage && (
                        <div className="flex justify-end pt-1 border-t border-border/40">
                            <button
                                type="button"
                                onClick={() => handleOpenPreview(fileState, existingUrl, labelKey)}
                                className="inline-flex items-center gap-1.5 rounded-md bg-background/80 px-2.5 py-1 text-xs font-medium text-primary shadow-sm hover:bg-accent hover:underline focus:outline-none transition-colors"
                            >
                                <EyeIcon className="size-3.5" />
                                {t('cms.banner.preview') || 'Preview'}
                            </button>
                        </div>
                    )}
                </div>
            </FormField>
        );
    };

    return (
        <CmsFormShell onSubmit={submit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <div className="col-span-full w-full space-y-6 sm:space-y-8">
                <section className="w-full rounded-xl border border-border/50 bg-card p-4 shadow-sm sm:p-6">
                    <FormField
                        label={t('cms.banner.type')}
                        htmlFor="banner-type-web-background"
                        error={fieldError('type')}
                        required
                    >
                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {bannerTypes.map((bannerType) => {
                                const isSelected = form.data.type === bannerType.value;
                                return (
                                    <label
                                        key={bannerType.value}
                                        className={cn(
                                            'relative flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all duration-200 select-none',
                                            isSelected
                                                ? 'border-primary/80 bg-primary/5 shadow-md shadow-primary/5 ring-2 ring-primary/20'
                                                : 'border-border/60 bg-background/50 hover:border-border hover:bg-accent/40',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="text-sm font-semibold text-foreground">
                                                {t(bannerType.labelKey)}
                                            </p>
                                            <input
                                                id={`banner-type-${bannerType.value}`}
                                                type="radio"
                                                name="banner_type"
                                                value={bannerType.value}
                                                checked={isSelected}
                                                onChange={() => {
                                                    markTouched('type');
                                                    setField('type', bannerType.value as BannerType);
                                                }}
                                                className="sr-only"
                                            />
                                            <div
                                                className={cn(
                                                    'flex size-5 shrink-0 items-center justify-center rounded-full border transition-all',
                                                    isSelected
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-muted-foreground/30 bg-background',
                                                )}
                                            >
                                                {isSelected && <CheckIcon className="size-3 stroke-[3]" />}
                                            </div>
                                        </div>

                                        <div className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground font-mono">
                                            <span className="rounded bg-muted/80 px-1.5 py-0.5 font-medium">
                                                {bannerType.width} × {bannerType.height} px
                                            </span>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                    </FormField>
                </section>

                <section className="w-full rounded-xl border border-border/50 bg-card p-4 shadow-sm sm:p-6">
                    <div className="mb-4 flex items-center justify-between border-b border-border/40 pb-3">
                        <div className="flex items-center gap-2">
                            <ImageIcon className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold text-foreground">{t('cms.banner.images')}</h3>
                        </div>
                        <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                            {t('cms.banner.image_size')} : {selectedBannerType.width} × {selectedBannerType.height} px
                        </span>
                    </div>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {renderImageField('image_url_en', 'cms.banner.image_en', imageEn, setImageEn, imageUrls?.en)}
                        {renderImageField('image_url_zh', 'cms.banner.image_zh', imageZh, setImageZh, imageUrls?.zh)}
                        {renderImageField('image_url_my', 'cms.banner.image_my', imageMy, setImageMy, imageUrls?.my)}
                    </div>
                </section>

                <div className="grid w-full grid-cols-1 gap-6 lg:grid-cols-2">
                    <section className="w-full rounded-xl border border-border/50 bg-card p-4 shadow-sm sm:p-6">
                        <div className="mb-4 flex items-center gap-2 border-b border-border/40 pb-3">
                            <Settings2Icon className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold text-foreground">{t('menu.settings')}</h3>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    </section>

                    <section className="w-full rounded-xl border border-border/50 bg-card p-4 shadow-sm sm:p-6">
                        <div className="mb-4 flex items-center gap-2 border-b border-border/40 pb-3">
                            <CalendarIcon className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold text-foreground">{t('cms.banner.schedule')}</h3>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    </section>
                </div>
            </div>

            {activePreview && (
                <BannerImagePreviewModal
                    preview={activePreview}
                    previewClassName={getPreviewSize()}
                    onClose={() => setActivePreview(null)}
                />
            )}
        </CmsFormShell>
    );
}

function BannerImagePreviewModal({
    preview,
    previewClassName,
    onClose,
}: {
    preview: PreviewModalState;
    previewClassName: string;
    onClose: () => void;
}) {
    const handleClose = () => {
        if (preview.url.startsWith('blob:')) {
            URL.revokeObjectURL(preview.url);
        }

        onClose();
    };

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                handleClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [preview.url]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80" onClick={handleClose}>
            <div className={cn('relative overflow-hidden', previewClassName)} onClick={(e) => e.stopPropagation()}>
                <img
                    src={preview.url}
                    alt={preview.title}
                    className="absolute inset-0 h-full w-full object-cover"
                    style={{
                        transform: 'scale(1.02)',
                    }}
                />

                <button
                    type="button"
                    onClick={handleClose}
                    className="absolute right-3 top-3 z-20 rounded-lg bg-black/60 p-2 text-white"
                >
                    <XIcon className="size-4" />
                </button>
            </div>
        </div>
    );
}
