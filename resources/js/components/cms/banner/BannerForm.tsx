import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CalendarClockIcon, CalendarIcon, CircleDotIcon, HashIcon, Link2Icon, TypeIcon } from 'lucide-react';

import { FormField } from '@/components/ui/form-field';
import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { CmsImageField } from '@/components/cms/shared/CmsImageField';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type BannerFormValues = {
    title: string;
    link_url: string;
    sort_order: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    image: File | null;
};

type BannerFormProps = {
    form: InertiaFormProps<BannerFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

export function BannerForm({ form, onSubmit, onCancel, mode = 'create', imageUrl }: BannerFormProps) {
    const { t } = useTranslation();

    return (
        <CmsFormShell onSubmit={onSubmit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <FormField label={t('cms.title')} htmlFor="title" error={form.errors.title} icon={TypeIcon} className="sm:col-span-2">
                <Input id="title" value={form.data.title} required onChange={(event) => form.setData('title', event.target.value)} />
            </FormField>
            <FormField label={t('cms.link_url')} htmlFor="link_url" error={form.errors.link_url} icon={Link2Icon} className="sm:col-span-2">
                <Input id="link_url" type="url" value={form.data.link_url} onChange={(event) => form.setData('link_url', event.target.value)} />
            </FormField>
            <FormField label={t('cms.sort_order')} htmlFor="sort_order" error={form.errors.sort_order} icon={HashIcon}>
                <Input id="sort_order" type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} />
            </FormField>
            <FormField label={t('common.status')} htmlFor="is_active" error={form.errors.is_active} icon={CircleDotIcon}>
                <Select value={form.data.is_active ? '1' : '0'} onValueChange={(value) => form.setData('is_active', value === '1')}>
                    <SelectTrigger id="is_active" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">{t('status.active')}</SelectItem>
                        <SelectItem value="0">{t('status.inactive')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField label={t('cms.start_date')} htmlFor="start_date" error={form.errors.start_date} icon={CalendarIcon}>
                <Input id="start_date" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} />
            </FormField>
            <FormField label={t('cms.end_date')} htmlFor="end_date" error={form.errors.end_date} icon={CalendarClockIcon}>
                <Input id="end_date" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} />
            </FormField>
            <CmsImageField error={form.errors.image} currentUrl={imageUrl} required={!imageUrl} onChange={(file) => form.setData('image', file)} />
        </CmsFormShell>
    );
}
