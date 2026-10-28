import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, FileTextIcon, FolderTreeIcon, Link2Icon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type NewsFormValues = {
    category_id: string;
    title_en: string;
    title_zh: string;
    title_my: string;
    slug: string;
    description_en: string;
    description_zh: string;
    description_my: string;
    status: string;
    image: File | null;
};

export type NewsOption = {
    id: number;
    name_en: string;
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
    cancelHref: string;
    categories: NewsOption[];
    imageUrl?: string | null;
};

export function NewsForm({ form, onSubmit, cancelHref, categories, imageUrl }: NewsFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.category')} htmlFor="category_id" error={form.errors.category_id} className="sm:col-span-2">
                <FormControl icon={FolderTreeIcon}>
                    <Select value={form.data.category_id} onValueChange={(value) => form.setData('category_id', value)}>
                        <SelectTrigger id="category_id" className="w-full" aria-invalid={Boolean(form.errors.category_id)}>
                            <SelectValue placeholder={t('cms.category')} />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem key={category.id} value={String(category.id)}>{category.name_en}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormControl>
                {categories.length === 0 ? <p className="mt-1 text-xs text-muted-foreground">{t('cms.no_categories')}</p> : null}
            </CmsField>
            <CmsField label={t('cms.title_en')} htmlFor="title_en" error={form.errors.title_en} icon={TypeIcon} className="sm:col-span-2">
                <Input
                    id="title_en"
                    value={form.data.title_en}
                    required
                    onChange={(event) => {
                        const title = event.target.value;
                        form.setData((data) => ({
                            ...data,
                            title_en: title,
                            slug: slugTouched ? data.slug : toSlug(title),
                        }));
                    }}
                />
            </CmsField>
            <CmsField label={t('cms.title_zh')} htmlFor="title_zh" error={form.errors.title_zh} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title_zh" value={form.data.title_zh} required onChange={(event) => form.setData('title_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.title_my')} htmlFor="title_my" error={form.errors.title_my} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title_my" value={form.data.title_my} required onChange={(event) => form.setData('title_my', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.slug')} htmlFor="slug" error={form.errors.slug} icon={Link2Icon}>
                <Input
                    id="slug"
                    value={form.data.slug}
                    required
                    onChange={(event) => {
                        setSlugTouched(true);
                        form.setData('slug', event.target.value);
                    }}
                />
            </CmsField>
            <CmsField label={t('common.status')} htmlFor="status" error={form.errors.status} icon={CircleDotIcon}>
                <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                    <SelectTrigger id="status" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="draft">{t('status.draft')}</SelectItem>
                        <SelectItem value="published">{t('status.published')}</SelectItem>
                        <SelectItem value="archived">{t('status.archived')}</SelectItem>
                    </SelectContent>
                </Select>
            </CmsField>
            <CmsField label={t('cms.description_en')} htmlFor="description_en" error={form.errors.description_en} icon={FileTextIcon} className="sm:col-span-2">
                <Textarea id="description_en" className="min-h-40" value={form.data.description_en} required onChange={(event) => form.setData('description_en', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description_zh')} htmlFor="description_zh" error={form.errors.description_zh} icon={FileTextIcon} className="sm:col-span-2">
                <Textarea id="description_zh" className="min-h-40" value={form.data.description_zh} required onChange={(event) => form.setData('description_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description_my')} htmlFor="description_my" error={form.errors.description_my} icon={FileTextIcon} className="sm:col-span-2">
                <Textarea id="description_my" className="min-h-40" value={form.data.description_my} required onChange={(event) => form.setData('description_my', event.target.value)} />
            </CmsField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
