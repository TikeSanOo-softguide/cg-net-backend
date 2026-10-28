import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CalendarClockIcon, CalendarIcon, CircleDotIcon, HashIcon } from 'lucide-react';

import { CmsField } from '@/components/cms/CmsField';
import { CmsFormShell } from '@/components/cms/CmsFormShell';
import { CmsImageField } from '@/components/cms/CmsImageField';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type BannerFormValues = {
    sort_order: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    image_en: File | null;
    image_zh: File | null;
    image_my: File | null;
};

type BannerFormProps = {
    form: InertiaFormProps<BannerFormValues>;
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    imageUrlEn?: string | null;
    imageUrlZh?: string | null;
    imageUrlMy?: string | null;
};

export function BannerForm({ form, onSubmit, cancelHref, imageUrlEn, imageUrlZh, imageUrlMy }: BannerFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} cancelHref={cancelHref} processing={form.processing}>
            <CmsField label={t('cms.sort_order')} htmlFor="sort_order" error={form.errors.sort_order} icon={HashIcon}>
                <Input id="sort_order" type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} />
            </CmsField>
            <CmsField label={t('common.status')} htmlFor="is_active" error={form.errors.is_active} icon={CircleDotIcon}>
                <Select value={form.data.is_active ? '1' : '0'} onValueChange={(value) => form.setData('is_active', value === '1')}>
                    <SelectTrigger id="is_active" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">{t('status.active')}</SelectItem>
                        <SelectItem value="0">{t('status.inactive')}</SelectItem>
                    </SelectContent>
                </Select>
            </CmsField>
            <CmsField label={t('cms.start_date')} htmlFor="start_date" error={form.errors.start_date} icon={CalendarIcon}>
                <Input id="start_date" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />
            </CmsField>
            <CmsField label={t('cms.end_date')} htmlFor="end_date" error={form.errors.end_date} icon={CalendarClockIcon}>
                <Input id="end_date" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />
            </CmsField>
            <CmsImageField label={t('cms.image_en')} inputId="image_en" error={form.errors.image_en} currentUrl={imageUrlEn} required={!imageUrlEn} onChange={(file) => form.setData('image_en', file)} />
            <CmsImageField label={t('cms.image_zh')} inputId="image_zh" error={form.errors.image_zh} currentUrl={imageUrlZh} required={!imageUrlZh} onChange={(file) => form.setData('image_zh', file)} />
            <CmsImageField label={t('cms.image_my')} inputId="image_my" error={form.errors.image_my} currentUrl={imageUrlMy} required={!imageUrlMy} onChange={(file) => form.setData('image_my', file)} />
        </CmsFormShell>
    );
}
