import { useState, type FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { validateAppVersion, validateAppVersionField } from '@/lib/app-version-validation';

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

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm<AppVersionFormData>(
        initialData
            ? {
                  ...initialData,
                  release_notes_en: initialData.release_notes_en ?? '',
                  release_notes_zh: initialData.release_notes_zh ?? '',
                  release_notes_my: initialData.release_notes_my ?? '',
              }
            : emptyForm(),
    );

    const [touched, setTouched] = useState<Record<keyof Omit<AppVersionFormData, 'id'>, boolean>>({
        platform: false,
        version: false,
        minimum_version: false,
        download_url: false,
        release_notes_en: false,
        release_notes_zh: false,
        release_notes_my: false,
        force_update: false,
        status: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof Omit<AppVersionFormData, 'id'>) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof AppVersionFormData>(field: K, value: AppVersionFormData[K]) => {
        setData(field, value);
        clearErrors(field as keyof AppVersionFormData);
    };

    const fieldState = (field: keyof Omit<AppVersionFormData, 'id'>): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return errors[field] || validateAppVersionField(field, data, t) ? 'error' : 'success';
    };

    const fieldError = (field: keyof Omit<AppVersionFormData, 'id'>): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return errors[field] || validateAppVersionField(field, data, t);
    };

    const handleSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            platform: true,
            version: true,
            minimum_version: true,
            download_url: true,
            release_notes_en: true,
            release_notes_zh: true,
            release_notes_my: true,
            force_update: true,
            status: true,
        });

        const validationErrors = validateAppVersion(data, t);
        if (Object.keys(validationErrors).length > 0) {
            setError(validationErrors);

            return;
        }

        clearErrors();

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
            <FormField label={t('common.platform')} htmlFor="platform" error={fieldError('platform')} required>
                <Select
                    value={data.platform}
                    onValueChange={(value) => {
                        setField('platform', value as AppVersionFormData['platform']);
                        markTouched('platform');
                    }}
                >
                    <SelectTrigger
                        id="platform"
                        aria-required="true"
                        aria-invalid={fieldState('platform') === 'error'}
                        className={formControlStateClass(fieldState('platform'))}
                    >
                        <SelectValue placeholder={t('common.platform')} />
                    </SelectTrigger>
                    <SelectContent className="[&_[data-slot=select-item]]:text-xs">
                        <SelectItem value="android">{t('common.platform_android')}</SelectItem>
                        <SelectItem value="ios">{t('common.platform_ios')}</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>

            {/* Force Update (Enable / Disable Toggle Switch) */}
            <FormField label={t('settings.app_version.force_update')} htmlFor="force_update" error={fieldError('force_update')} required>
                <div
                    className={`flex h-10 items-center justify-between rounded-md border bg-background px-3 transition-colors duration-200 ${
                        fieldState('force_update') === 'error' ? 'border-danger' : 'border-border'
                    }`}
                >
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
                        aria-required="true"
                        aria-invalid={fieldState('force_update') === 'error'}
                        onClick={() => {
                            setField('force_update', !data.force_update);
                            markTouched('force_update');
                        }}
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
            <FormField label={t('settings.app_version.version')} htmlFor="version" error={fieldError('version')} required>
                <Input
                    id="version"
                    value={data.version}
                   
                    aria-invalid={fieldState('version') === 'error'}
                    className={formControlStateClass(fieldState('version'))}
                    onBlur={() => markTouched('version')}
                    onChange={(event) => {
                        setField('version', event.target.value);
                        markTouched('version');
                    }}
                    placeholder="1.0.0"
                />
            </FormField>

            {/* Minimum Version */}
            <FormField
                label={t('settings.app_version.minimum_version')}
                htmlFor="minimum_version"
                error={fieldError('minimum_version')}
                
            >
                <Input
                    id="minimum_version"
                    value={data.minimum_version}
                    
                    aria-invalid={fieldState('minimum_version') === 'error'}
                    className={formControlStateClass(fieldState('minimum_version'))}
                    onBlur={() => markTouched('minimum_version')}
                    onChange={(event) => {
                        setField('minimum_version', event.target.value);
                        markTouched('minimum_version');
                    }}
                    placeholder="1.0.0"
                />
            </FormField>

            {/* Status (Version အောက်တွင် Active / Inactive Switch ဖြင့် ထည့်သွင်းထားခြင်း) */}
            <FormField label={t('common.status')} htmlFor="status" error={fieldError('status')} className="sm:col-span-2" required>
                <StaffStatusSwitch
                    id="status"
                    value={data.status}
                    onChange={(value) => {
                        setField('status', value as 'active' | 'inactive');
                        markTouched('status');
                    }}
                />
            </FormField>

            {/* Download URL */}
            <FormField
                label={t('common.link')}
                htmlFor="download_url"
                error={fieldError('download_url')}
                className="sm:col-span-2"
                required
             
            >
                <Input
                    id="download_url"
                    type="url"
                    value={data.download_url}
                    
                    aria-invalid={fieldState('download_url') === 'error'}
                    className={formControlStateClass(fieldState('download_url'))}
                    onBlur={() => markTouched('download_url')}
                    onChange={(event) => {
                        setField('download_url', event.target.value);
                        markTouched('download_url');
                    }}
                    placeholder="https://..."
                />
            </FormField>

            {/* Release Notes (EN) */}
            <FormField
                label={t('settings.app_version.release_notes_en')}
                htmlFor="release_notes_en"
                error={fieldError('release_notes_en')}
                className="sm:col-span-2"
                required
               
            >
                <Textarea
                    id="release_notes_en"
                    rows={2}
                    value={data.release_notes_en}
                    
                    aria-invalid={fieldState('release_notes_en') === 'error'}
                    className={formControlStateClass(fieldState('release_notes_en'))}
                    onBlur={() => markTouched('release_notes_en')}
                    onChange={(event) => {
                        setField('release_notes_en', event.target.value);
                        markTouched('release_notes_en');
                    }}
                    placeholder={t('settings.app_version.placeholder_release_notes_en')}
                />
            </FormField>

            {/* Release Notes (ZH) */}
            <FormField
                label={t('settings.app_version.release_notes_zh')}
                htmlFor="release_notes_zh"
                error={fieldError('release_notes_zh')}
                className="sm:col-span-2"
                required
                
            >
                <Textarea
                    id="release_notes_zh"
                    rows={2}
                    value={data.release_notes_zh}
                    
                    aria-invalid={fieldState('release_notes_zh') === 'error'}
                    className={formControlStateClass(fieldState('release_notes_zh'))}
                    onBlur={() => markTouched('release_notes_zh')}
                    onChange={(event) => {
                        setField('release_notes_zh', event.target.value);
                        markTouched('release_notes_zh');
                    }}
                    placeholder={t('settings.app_version.placeholder_release_notes_zh')}
                />
            </FormField>

            {/* Release Notes (MY) */}
            <FormField
                label={t('settings.app_version.release_notes_my')}
                htmlFor="release_notes_my"
                error={fieldError('release_notes_my')}
                className="sm:col-span-2"
                required
               
            >
                <Textarea
                    id="release_notes_my"
                    rows={2}
                    value={data.release_notes_my}
                    
                    aria-invalid={fieldState('release_notes_my') === 'error'}
                    className={formControlStateClass(fieldState('release_notes_my'))}
                    onBlur={() => markTouched('release_notes_my')}
                    onChange={(event) => {
                        setField('release_notes_my', event.target.value);
                        markTouched('release_notes_my');
                    }}
                    placeholder={t('settings.app_version.placeholder_release_notes_my')}
                />
            </FormField>
        </CmsFormShell>
    );
}
