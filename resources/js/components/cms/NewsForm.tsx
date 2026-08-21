import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, FileTextIcon, FolderTreeIcon, Link2Icon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { CmsLanguageField } from '@/components/cms/CmsLanguageField';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type NewsFormValues = {
    category_id: string;
    title: string;
    slug: string;
    content: string;
    status: string;
    lang: string;
    image: File | null;
    tag_ids: number[];
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
    cancelHref: string;
    categories: NewsOption[];
    tags: NewsOption[];
    imageUrl?: string | null;
};

export function NewsForm({ form, onSubmit, cancelHref, categories, tags, imageUrl }: NewsFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');

    const toggleTag = (id: number) => {
        const selected = form.data.tag_ids.includes(id)
            ? form.data.tag_ids.filter((tagId) => tagId !== id)
            : [...form.data.tag_ids, id];

        form.setData('tag_ids', selected);
    };

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
                                <SelectItem key={category.id} value={String(category.id)}>{category.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormControl>
                {categories.length === 0 ? <p className="mt-1 text-xs text-muted-foreground">{t('cms.no_categories')}</p> : null}
            </CmsField>
            <CmsField label={t('cms.title')} htmlFor="title" error={form.errors.title} icon={TypeIcon} className="sm:col-span-2">
                <Input
                    id="title"
                    value={form.data.title}
                    required
                    onChange={(event) => {
                        form.setData('title', event.target.value);
                        if (! slugTouched) {
                            form.setData('slug', toSlug(event.target.value));
                        }
                    }}
                />
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
            <CmsLanguageField value={form.data.lang} error={form.errors.lang} onChange={(value) => form.setData('lang', value)} />
            <CmsField label={t('cms.content')} htmlFor="content" error={form.errors.content} icon={FileTextIcon} className="sm:col-span-2">
                <Textarea id="content" className="min-h-40" value={form.data.content} required onChange={(event) => form.setData('content', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.tags')} htmlFor="tag_ids" error={form.errors.tag_ids} className="sm:col-span-2">
                <div className="flex flex-wrap gap-2">
                    {tags.length === 0 ? (
                        <p className="text-xs text-muted-foreground">{t('common.no_results')}</p>
                    ) : tags.map((tag) => (
                        <label key={tag.id} className="inline-flex items-center gap-2 rounded-[6px] border border-border px-2.5 py-1.5 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.tag_ids.includes(tag.id)}
                                onChange={() => toggleTag(tag.id)}
                                className="size-4 rounded-[4px] border border-input accent-primary focus-visible:border-primary focus-visible:ring-1 focus-visible:ring-primary/40 focus-visible:outline-none"
                            />
                            {tag.name}
                        </label>
                    ))}
                </div>
            </CmsField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
