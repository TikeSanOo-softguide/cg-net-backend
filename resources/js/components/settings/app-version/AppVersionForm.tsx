import { type FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

export type AppVersionFormData = {
    id?: number;
    platform: 'android' | 'ios';
    version: string;
    minimum_version: string;
    download_url: string;
    release_notes_en: string;
    release_notes_zh: string;
    release_notes_my: string;
    force_update: boolean;
    status: 'active' | 'inactive';
};

type Props = {
    initialData?: AppVersionFormData | null;
    onSuccess: () => void;
    onCancel?: () => void;
};

function emptyForm(): AppVersionFormData {
    return {
        platform: 'android',
        version: '',
        minimum_version: '',
        download_url: '',
        release_notes_en: '',
        release_notes_zh: '',
        release_notes_my: '',
        force_update: false,
        status: 'active',
    };
}

export function AppVersionForm({ initialData, onSuccess, onCancel }: Props) {
    const { t } = useTranslation();
    const isEdit = Boolean(initialData?.id);

    const { data, setData, post, put, processing, errors } = useForm<AppVersionFormData>(
        initialData
            ? {
                  ...initialData,
                  release_notes_en: initialData.release_notes_en ?? '',
                  release_notes_zh: initialData.release_notes_zh ?? '',
                  release_notes_my: initialData.release_notes_my ?? '',
              }
            : emptyForm(),
    );

    const handleSubmit: FormEventHandler = (event) => {
        event.preventDefault();

        if (isEdit && initialData?.id) {
            put(`/settings/app-version/${initialData.id}`, { onSuccess, preserveScroll: true });

            return;
        }

        post('/settings/app-version', { onSuccess, preserveScroll: true });
    };

    return (
        <CmsFormShell
            onSubmit={handleSubmit}
            onCancel={onCancel}
            processing={processing}
            mode={isEdit ? 'edit' : 'create'}
        >
            {/* Platform Selection */}
            <FormField label={t('common.platform')} htmlFor="platform" error={errors.platform}>
                <Select
                    value={data.platform}
                    onValueChange={(value) => setData('platform', value as AppVersionFormData['platform'])}
                >
                    <SelectTrigger id="platform" className="w-full">
                        <SelectValue placeholder={t('common.platform')} />
                    </SelectTrigger>
                    <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                        <SelectItem value="android">{t('common.platform_android')}</SelectItem>
                        <SelectItem value="ios">{t('common.platform_ios')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>

            {/* Force Update (Enable / Disable Toggle Switch) */}
            <FormField label={t('app_version.force_update')} htmlFor="force_update" error={errors.force_update}>
                <div className="flex h-10 items-center justify-between rounded-md border border-border bg-background px-3">
                    <span
                        className={`text-xs font-medium ${data.force_update ? 'text-primary' : 'text-muted-foreground'}`}
                    >
                        {data.force_update ? t('common.enabled') : t('common.disabled')}
                    </span>
                    <button
                        type="button"
                        id="force_update"
                        role="switch"
                        aria-checked={data.force_update}
                        onClick={() => setData('force_update', !data.force_update)}
                        className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
                            data.force_update ? 'bg-primary' : 'bg-muted'
                        }`}
                    >
                        <span
                            className={`pointer-events-none inline-block size-4 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out ${
                                data.force_update ? 'translate-x-4' : 'translate-x-0'
                            }`}
                        />
                    </button>
                </div>
            </FormField>

            {/* Version */}
            <FormField label={t('app_version.version')} htmlFor="version" error={errors.version}>
                <Input
                    id="version"
                    value={data.version}
                    onChange={(event) => setData('version', event.target.value)}
                    placeholder="1.0.0"
                />
            </FormField>

            {/* Minimum Version */}
            <FormField
                label={t('app_version.minimum_version')}
                htmlFor="minimum_version"
                error={errors.minimum_version}
            >
                <Input
                    id="minimum_version"
                    value={data.minimum_version}
                    onChange={(event) => setData('minimum_version', event.target.value)}
                    placeholder="1.0.0"
                />
            </FormField>

            {/* Status (Version အောက်တွင် Active / Inactive Switch ဖြင့် ထည့်သွင်းထားခြင်း) */}
            <FormField label={t('common.status')} htmlFor="status" error={errors.status} className="sm:col-span-2">
                <StaffStatusSwitch
                    id="status"
                    value={data.status}
                    onChange={(value) => setData('status', value as 'active' | 'inactive')}
                />
            </FormField>

            {/* Download URL */}
            <FormField
                label={t('common.link')}
                htmlFor="download_url"
                error={errors.download_url}
                className="sm:col-span-2"
            >
                <Input
                    id="download_url"
                    value={data.download_url}
                    onChange={(event) => setData('download_url', event.target.value)}
                    placeholder="https://..."
                />
            </FormField>

            {/* Release Notes (EN) */}
            <FormField
                label={t('app_version.release_notes_en')}
                htmlFor="release_notes_en"
                error={errors.release_notes_en}
                className="sm:col-span-2"
            >
                <Textarea
                    id="release_notes_en"
                    rows={2}
                    value={data.release_notes_en}
                    onChange={(event) => setData('release_notes_en', event.target.value)}
                    placeholder={t('app_version.placeholder_release_notes_en')}
                    className="text-xs"
                />
            </FormField>

            {/* Release Notes (ZH) */}
            <FormField
                label={t('app_version.release_notes_zh')}
                htmlFor="release_notes_zh"
                error={errors.release_notes_zh}
                className="sm:col-span-2"
            >
                <Textarea
                    id="release_notes_zh"
                    rows={2}
                    value={data.release_notes_zh}
                    onChange={(event) => setData('release_notes_zh', event.target.value)}
                    placeholder={t('app_version.placeholder_release_notes_zh')}
                    className="text-xs"
                />
            </FormField>

            {/* Release Notes (MY) */}
            <FormField
                label={t('app_version.release_notes_my')}
                htmlFor="release_notes_my"
                error={errors.release_notes_my}
                className="sm:col-span-2"
            >
                <Textarea
                    id="release_notes_my"
                    rows={2}
                    value={data.release_notes_my}
                    onChange={(event) => setData('release_notes_my', event.target.value)}
                    placeholder={t('app_version.placeholder_release_notes_my')}
                    className="text-xs"
                />
            </FormField>
        </CmsFormShell>
    );
}
