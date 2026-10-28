import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Link2Icon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type NameSlugFormValues = {
    name_en: string;
    name_zh: string;
    name_my: string;
    slug: string;
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

type NameSlugFormProps = {
    form: InertiaFormProps<NameSlugFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
};

export function NameSlugForm({ form, onSubmit, cancelHref }: NameSlugFormProps) {
    const { t } = useTranslation();
    const [slugTouched, setSlugTouched] = useState(form.data.slug !== '');

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.name_en')} htmlFor="name_en" error={form.errors.name_en} icon={TypeIcon} className="sm:col-span-2">
                <Input
                    id="name_en"
                    value={form.data.name_en}
                    required
                    onChange={(event) => {
                        const name = event.target.value;
                        form.setData((data) => ({
                            ...data,
                            name_en: name,
                            slug: slugTouched ? data.slug : toSlug(name),
                        }));
                    }}
                />
            </CmsField>
            <CmsField label={t('cms.name_zh')} htmlFor="name_zh" error={form.errors.name_zh} icon={TypeIcon} className="sm:col-span-2">
                <Input id="name_zh" value={form.data.name_zh} required onChange={(event) => form.setData('name_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.name_my')} htmlFor="name_my" error={form.errors.name_my} icon={TypeIcon} className="sm:col-span-2">
                <Input id="name_my" value={form.data.name_my} required onChange={(event) => form.setData('name_my', event.target.value)} />
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
        </CmsFormShell>
    );
}
