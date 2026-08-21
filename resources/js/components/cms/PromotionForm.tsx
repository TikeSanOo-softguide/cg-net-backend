import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { AlignLeftIcon, CalendarClockIcon, CalendarIcon, CircleDotIcon, TypeIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { CmsLanguageField } from '@/components/cms/CmsLanguageField';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type PromotionFormValues = {
    title: string;
    description: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    lang: string;
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
            <CmsField label={t('cms.title')} htmlFor="title" error={form.errors.title} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title" value={form.data.title} required onChange={(event) => form.setData('title', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.description')} htmlFor="description" error={form.errors.description} icon={AlignLeftIcon} className="sm:col-span-2">
                <Textarea id="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
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
            <CmsLanguageField value={form.data.lang} error={form.errors.lang} onChange={(value) => form.setData('lang', value)} />
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={! imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
