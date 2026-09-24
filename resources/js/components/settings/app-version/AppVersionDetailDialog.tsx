import { ExternalLinkIcon, SmartphoneIcon } from 'lucide-react';

import {
    FormActionBar,
    formActionBarClass,
    formActionCancelClass,
    formActionSubmitClass,
} from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

import type { AppVersionFormData } from './AppVersionForm';

type AppVersionDetailDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: AppVersionFormData | null;
    onEdit: (item: AppVersionFormData) => void;
};

export function AppVersionDetailDialog({ open, onOpenChange, item, onEdit }: AppVersionDetailDialogProps) {
    const { t } = useTranslation();

    if (!item) return null;

    const platformLabels: Record<string, string> = {
        android: t('common.platform_android') || 'Android',
        ios: t('common.platform_ios') || 'iOS',
    };

    const isForced = Boolean(item.force_update);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('app_version.details') || 'App Version Details'}
            description={`${t('app_version.label') || 'App version'} • ${item.version} (${platformLabels[item.platform] || item.platform})`}
            icon={SmartphoneIcon}
            size="xl"
        >
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <label className="text-[12px] font-medium text-foreground">{t('common.platform')}</label>
                        <div className="flex h-10 items-center rounded-md border border-border bg-muted/20 px-3 text-xs font-medium">
                            {platformLabels[item.platform] || item.platform}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <label className="text-[12px] font-medium text-foreground">
                            {t('app_version.force_update')}
                        </label>
                        <div className="flex h-10 items-center justify-between rounded-md border border-border bg-muted/20 px-3">
                            <span
                                className={`text-xs font-medium ${isForced ? 'text-primary' : 'text-muted-foreground'}`}
                            >
                                {isForced ? t('common.enabled') || 'Enabled' : t('common.disabled') || 'Disabled'}
                            </span>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                    isForced
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'
                                }`}
                            >
                                {isForced ? t('common.enabled') || 'Enabled' : t('common.disabled') || 'Disabled'}
                            </span>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <label className="text-[12px] font-medium text-foreground">{t('app_version.version')}</label>
                        <div className="flex h-10 items-center rounded-md border border-border bg-muted/20 px-3 text-xs font-medium">
                            {item.version || '—'}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <label className="text-[12px] font-medium text-foreground">
                            {t('app_version.minimum_version')}
                        </label>
                        <div className="flex h-10 items-center rounded-md border border-border bg-muted/20 px-3 text-xs font-medium">
                            {item.minimum_version || '—'}
                        </div>
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                        <label className="text-[12px] font-medium text-foreground">{t('common.status')}</label>
                        <div className="flex h-10 items-center rounded-md border border-border bg-muted/20 px-3">
                            <StatusBadge status={item.status} className="text-[11px]" />
                        </div>
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                        <label className="text-[12px] font-medium text-foreground">{t('common.link')}</label>
                        <div className="flex h-10 items-center justify-between gap-2 rounded-md border border-border bg-muted/20 px-3 text-xs">
                            <span className="truncate font-mono text-muted-foreground">{item.download_url || '—'}</span>
                            {item.download_url ? (
                                <a
                                    href={item.download_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex shrink-0 items-center gap-1 font-medium text-primary hover:underline"
                                >
                                    <span>Open</span>
                                    <ExternalLinkIcon className="size-3.5" />
                                </a>
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                        <label className="text-[12px] font-medium text-foreground">
                            {t('app_version.release_notes_en')}
                        </label>
                        <div className="min-h-[52px] rounded-md border border-border bg-muted/20 p-2.5 text-xs leading-relaxed text-foreground whitespace-pre-wrap">
                            {item.release_notes_en || <span className="text-muted-foreground">—</span>}
                        </div>
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                        <label className="text-[12px] font-medium text-foreground">
                            {t('app_version.release_notes_zh')}
                        </label>
                        <div className="min-h-[52px] rounded-md border border-border bg-muted/20 p-2.5 text-xs leading-relaxed text-foreground whitespace-pre-wrap">
                            {item.release_notes_zh || <span className="text-muted-foreground">—</span>}
                        </div>
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                        <label className="text-[12px] font-medium text-foreground">
                            {t('app_version.release_notes_my')}
                        </label>
                        <div className="min-h-[52px] rounded-md border border-border bg-muted/20 p-2.5 text-xs leading-relaxed text-foreground whitespace-pre-wrap">
                            {item.release_notes_my || <span className="text-muted-foreground">—</span>}
                        </div>
                    </div>
                </div>
            </div>

            <div className={formActionBarClass}>
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    className={formActionCancelClass}
                    onClick={() => onOpenChange(false)}
                >
                    {t('common.close')}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="primary"
                    className={formActionSubmitClass}
                    onClick={() => onEdit(item)}
                >
                    {t('common.edit')}
                </Button>
            </div>
        </FormDialog>
    );
}
