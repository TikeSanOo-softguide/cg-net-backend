import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, FileTextIcon, FolderTreeIcon, Link2Icon, TypeIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { validateNews, validateNewsField } from '@/lib/news-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type NewsFormValues = {
    category_id: string;
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
    slug: string;
    status: string;
    image: File | null;
    image_url: string | null;
};

export type NewsOption = {
    id: number;
    name: string;
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

type NewsFormProps = {
    form: InertiaFormProps<NewsFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    onImageClear: () => void;
    mode?: 'create' | 'edit';
    categories: NewsOption[];
    imageUrl?: string | null;
};

export function NewsForm({
    form,
    onSubmit,
    onCancel,
    onImageClear,
    mode = 'create',
    categories,
    imageUrl,
}: NewsFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');
    const [image, setImage] = useState<File | null>(null);
    const [touched, setTouched] = useState<Record<keyof NewsFormValues, boolean>>({
        category_id: false,
        title_en: false,
        title_zh: false,
        title_my: false,
        description_en: false,
        description_zh: false,
        description_my: false,
        slug: false,
        status: false,
        image: false,
        image_url: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof NewsFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof NewsFormValues>(field: K, value: NewsFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof NewsFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || validateNewsField(field, form.data, t) ? 'error' : 'success';
    };

    const fieldError = (field: keyof NewsFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || validateNewsField(field, form.data, t);
    };

    const handleImageDelete = (file: File | null) => {
        if (!file) {
            setField('image_url', '');
            onImageClear();
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            category_id: true,
            title_en: true,
            title_zh: true,
            title_my: true,
            description_en: true,
            description_zh: true,
            description_my: true,
            slug: true,
            status: true,
            image: true,
            image_url: true,
        });

        const errors = validateNews(form.data, t);

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
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_en"
                        className={cn('h-40', formControlStateClass(fieldState('description_en')))}
                        value={form.data.description_en}
                        aria-invalid={fieldState('description_en') === 'error'}
                        onBlur={() => markTouched('description_en')}
                        onChange={(event) => setField('description_en', event.target.value)}
                    />
                </FormField>
            </div>
            <div className="md:ml-3">
                <FormField label={t('cms.image')} htmlFor="image" error={fieldError('image')} className="mb-2">
                    <SquareImageUpload
                        id="image"
                        width={620}
                        height={260}
                        value={image}
                        existingUrl={imageUrl}
                        className={cn('w-full', formControlStateClass(fieldState('image')))}
                        onChange={(file) => {
                            setImage(file);
                            setField('image', file);
                            markTouched('image');
                            handleImageDelete(file);
                        }}
                    />
                </FormField>
            </div>
            <div>
                <FormField
                    label={t('cms.news.title_zh')}
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
                    label={t('cms.news.description_zh')}
                    htmlFor="description_zh"
                    error={fieldError('description_zh')}
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_zh"
                        className={cn('h-40', formControlStateClass(fieldState('description_zh')))}
                        value={form.data.description_zh}
                        aria-invalid={fieldState('description_zh') === 'error'}
                        onBlur={() => markTouched('description_zh')}
                        onChange={(event) => setField('description_zh', event.target.value)}
                    />
                </FormField>
            </div>
            <div className="md:ml-3">
                <FormField
                    label={t('cms.news.title_my')}
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
                    label={t('cms.news.description_my')}
                    htmlFor="description_my"
                    error={fieldError('description_my')}
                    required
                    icon={FileTextIcon}
                    className="mb-3"
                >
                    <Textarea
                        id="description_my"
                        className={cn('h-40', formControlStateClass(fieldState('description_my')))}
                        value={form.data.description_my}
                        aria-invalid={fieldState('description_my') === 'error'}
                        onBlur={() => markTouched('description_my')}
                        onChange={(event) => setField('description_my', event.target.value)}
                    />
                </FormField>
            </div>
            <div>
                <FormField label={t('cms.slug')} htmlFor="slug" error={fieldError('slug')} required icon={Link2Icon}>
                    <Input
                        id="slug"
                        value={form.data.slug}
                        aria-invalid={fieldState('slug') === 'error'}
                        className={cn('w-full', formControlStateClass(fieldState('slug')))}
                        onBlur={() => markTouched('slug')}
                        onChange={(event) => {
                            setSlugTouched(true);
                            setField('slug', event.target.value);
                        }}
                    />
                </FormField>
            </div>
            <div className="md:ml-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                <FormField
                    label={t('cms.category.label')}
                    htmlFor="category_id"
                    error={fieldError('category_id')}
                    required
                >
                    <FormControl icon={FolderTreeIcon}>
                        <Select
                            value={form.data.category_id}
                            onValueChange={(value) => {
                                setField('category_id', value);
                                markTouched('category_id');
                            }}
                        >
                            <SelectTrigger
                                id="category_id"
                                className={cn('w-full', formControlStateClass(fieldState('category_id')))}
                                aria-invalid={fieldState('category_id') === 'error'}
                            >
                                <SelectValue placeholder={t('cms.category.label')} />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem key={category.id} value={String(category.id)}>
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormControl>
                    {categories.length === 0 ? (
                        <p className="mt-1 text-xs text-muted-foreground">{t('cms.category.no_categories')}</p>
                    ) : null}
                </FormField>
                <FormField
                    label={t('common.status')}
                    htmlFor="status"
                    error={fieldError('status')}
                    required
                    icon={CircleDotIcon}
                >
                    <Select
                        value={form.data.status}
                        onValueChange={(value) => {
                            setField('status', value);
                            markTouched('status');
                        }}
                    >
                        <SelectTrigger
                            id="status"
                            className={cn('w-full', formControlStateClass(fieldState('status')))}
                            aria-invalid={fieldState('status') === 'error'}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">{t('status.draft')}</SelectItem>
                            <SelectItem value="published">{t('status.published')}</SelectItem>
                            <SelectItem value="archived">{t('status.archived')}</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
            </div>
        </CmsFormShell>
    );
}
