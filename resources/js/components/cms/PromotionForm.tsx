import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { AlignLeftIcon, CalendarClockIcon, CalendarIcon, CircleDotIcon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type PromotionFormValues = {
    title_en: string;
    title_zh: string;
    title_my: string;
    description_en: string;
    description_zh: string;
    description_my: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    image: File | null;
};

type PromotionFormProps = {
    form: InertiaFormProps<PromotionFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    imageUrl?: string | null;
};

export function PromotionForm({ form, onSubmit, cancelHref, imageUrl }: PromotionFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.title_en')} htmlFor="title_en" error={form.errors.title_en} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title_en" value={form.data.title_en} required onChange={(event) => form.setData('title_en', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.title_zh')} htmlFor="title_zh" error={form.errors.title_zh} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title_zh" value={form.data.title_zh} required onChange={(event) => form.setData('title_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.title_my')} htmlFor="title_my" error={form.errors.title_my} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title_my" value={form.data.title_my} required onChange={(event) => form.setData('title_my', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description_en')} htmlFor="description_en" error={form.errors.description_en} icon={AlignLeftIcon} className="sm:col-span-2">
                <Textarea id="description_en" value={form.data.description_en} required onChange={(event) => form.setData('description_en', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description_zh')} htmlFor="description_zh" error={form.errors.description_zh} icon={AlignLeftIcon} className="sm:col-span-2">
                <Textarea id="description_zh" value={form.data.description_zh} required onChange={(event) => form.setData('description_zh', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description_my')} htmlFor="description_my" error={form.errors.description_my} icon={AlignLeftIcon} className="sm:col-span-2">
                <Textarea id="description_my" value={form.data.description_my} required onChange={(event) => form.setData('description_my', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.start_date')} htmlFor="start_date" error={form.errors.start_date} icon={CalendarIcon}>
                <Input id="start_date" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.end_date')} htmlFor="end_date" error={form.errors.end_date} icon={CalendarClockIcon}>
                <Input id="end_date" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />
            </CmsField>
            <CmsField label={t('common.status')} htmlFor="is_active" error={form.errors.is_active} icon={CircleDotIcon}>
                <Select value={form.data.is_active ? '1' : '0'} onValueChange={(value) => form.setData('is_active', value === '1')}>
                    <SelectTrigger id="is_active" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">{t('status.active')}</SelectItem>
                        <SelectItem value="0">{t('status.inactive')}</SelectItem>
                    </SelectContent>
                </Select>
            </CmsField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={!imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
