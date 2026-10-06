import { Head, router } from '@inertiajs/react';
import { BanIcon, CalendarClockIcon } from 'lucide-react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { PushNotificationForm } from '@/components/notification/push/PushNotificationForm';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Card } from '@/components/ui/card';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDate } from '@/lib/utils';

export type PushScheduleItem = {
    id: number;
    title_en: string;
    title_zh: string;
    title_my: string;
    scheduled_at: string | null;
    status: 'pending' | 'sent' | 'failed' | 'cancelled';
    error_message: string | null;
    created_at: string | null;
};

type Props = {
    schedules: PushScheduleItem[];
};

function formatScheduleTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(date);
}

export default function PushComposeIndex({ schedules }: Props) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const [cancelId, setCancelId] = useState<number | null>(null);

    const localizedTitle = (row: PushScheduleItem): string => {
        if (locale === 'my') {
            return row.title_my || row.title_en || '—';
        }
        if (locale === 'zh') {
            return row.title_zh || row.title_en || '—';
        }
        return row.title_en || '—';
    };

    return (
        <>
            <Head title={t('menu.push_composer')} />
            <PageContent>
                <PageHeader />

                {can('notifications.create') ? (
                    <Card className="gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                        <div className="border-b border-border/70 px-4 py-3.5 sm:px-5">
                            <h2 className="text-[14px] font-semibold text-foreground">
                                {t('notification.push.composer_title')}
                            </h2>
                            <p className="mt-0.5 text-[12px] text-muted-foreground">
                                {t('notification.push.composer_description')}
                            </p>
                        </div>
                        <div className="px-4 py-4 sm:px-5 sm:py-5">
                            <PushNotificationForm />
                        </div>
                    </Card>
                ) : null}

                {schedules.length > 0 ? (
                    <Card className="gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                        <div className="flex items-center gap-2 border-b border-border/70 px-4 py-3.5 sm:px-5">
                            <span className="flex size-7 items-center justify-center rounded-[6px] bg-primary/12 text-primary">
                                <CalendarClockIcon className="size-3.5" strokeWidth={1.9} />
                            </span>
                            <div>
                                <h2 className="text-[14px] font-semibold text-foreground">
                                    {t('notification.push.scheduled_list')}
                                </h2>
                                <p className="text-[12px] text-muted-foreground">
                                    {t('notification.push.scheduled_list_description')}
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-border/70 bg-muted/30 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        <th className="px-4 py-2.5 sm:px-5">{t('notification.push.title')}</th>
                                        <th className="px-4 py-2.5 sm:px-5">{t('notification.push.schedule_date')}</th>
                                        <th className="px-4 py-2.5 sm:px-5">{t('notification.push.schedule_time')}</th>
                                        <th className="px-4 py-2.5 sm:px-5">{t('common.status')}</th>
                                        <th className="px-4 py-2.5 text-right sm:px-5">{t('common.actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {schedules.map((item) => (
                                        <tr key={item.id} className="border-b border-border/50 last:border-b-0">
                                            <td className="px-4 py-3 sm:px-5">
                                                <p className="max-w-[280px] truncate text-[13px] font-medium text-foreground">
                                                    {localizedTitle(item)}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-[13px] text-foreground sm:px-5">
                                                {formatDate(item.scheduled_at)}
                                            </td>
                                            <td className="px-4 py-3 text-[13px] text-foreground sm:px-5">
                                                {formatScheduleTime(item.scheduled_at)}
                                            </td>
                                            <td className="px-4 py-3 sm:px-5">
                                                <StatusBadge status={item.status} />
                                            </td>
                                            <td className="px-4 py-3 text-right sm:px-5">
                                                {item.status === 'pending' && can('notifications.update') ? (
                                                    <TableActionButton
                                                        label={t('notification.push.cancel')}
                                                        icon={BanIcon}
                                                        tone="danger"
                                                        onClick={() => setCancelId(item.id)}
                                                    />
                                                ) : (
                                                    <span className="text-[12px] text-muted-foreground">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                ) : null}
            </PageContent>

            <ConfirmDialog
                open={cancelId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCancelId(null);
                    }
                }}
                title={t('notification.push.cancel_title')}
                description={t('notification.push.cancel_description')}
                destructive
                confirmLabel={t('notification.push.cancel')}
                onConfirm={() => {
                    if (cancelId === null) {
                        return;
                    }

                    router.post(
                        `/notifications/compose/${cancelId}/cancel`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setCancelId(null),
                        },
                    );
                }}
            />
        </>
    );
}
