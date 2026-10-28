import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { TagIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

export type GalleryFormValues = {
    label_en: string;
    label_zh: string;
    label_my: string;
    image: File | null;
};

type GalleryFormProps = {
    form: InertiaFormProps<GalleryFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    imageUrl?: string | null;
};

export function GalleryForm({ form, onSubmit, cancelHref, imageUrl }: GalleryFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.label_en')} htmlFor="label_en" error={form.errors.label_en} icon={TagIcon} className="sm:col-span-2">
                <Input id="label_en" value={form.data.label_en} onChange={(event) => form.setData('label_en', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.label_zh')} htmlFor="label_zh" error={form.errors.label_zh} icon={TagIcon} className="sm:col-span-2">
                <Input id="label_zh" value={form.data.label_zh} onChange={(event) => form.setData('label_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.label_my')} htmlFor="label_my" error={form.errors.label_my} icon={TagIcon} className="sm:col-span-2">
                <Input id="label_my" value={form.data.label_my} onChange={(event) => form.setData('label_my', event.target.value)} />
            </CmsField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={!imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
