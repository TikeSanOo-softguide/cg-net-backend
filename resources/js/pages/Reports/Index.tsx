import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { ReportCard } from '@/components/Reports/ReportCard';
import { useTranslation } from '@/hooks/useTranslation';
import { reports } from '@/lib/navigation';
import { Head, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

type Props = {};

export default function ReportsIndex({}: Props) {
    const { t } = useTranslation();
    const { unreadAdminNotificationReferences = [] } = usePage<PageProps>().props;
    const hasUnreadLedgerHealth = [
        'ledger_health_snapshot_full',
        'ledger_health_snapshot_daily',
        'ledger_health_snapshot_manual',
    ].some((referenceType) => unreadAdminNotificationReferences.includes(referenceType));

    return (
        <>
            <Head title={t('menu.reports')} />
            <PageContent className="gap-3 lg:gap-3.5">
                <PageHeader />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {reports.map((report) => (
                        <ReportCard
                            key={report.href}
                            label={t(report.labelKey)}
                            description={t(report.descriptionKey)}
                            href={report.href}
                            icon={report.icon}
                            unreadLabel={
                                report.href === '/reports/ledger-health' && hasUnreadLedgerHealth
                                    ? t('admin_notifications.unread')
                                    : undefined
                            }
                        />
                    ))}
                </div>
            </PageContent>
        </>
    );
}
