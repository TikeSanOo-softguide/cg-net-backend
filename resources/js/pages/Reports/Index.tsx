import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { ReportCard } from '@/components/Reports/ReportCard';
import { useTranslation } from '@/hooks/useTranslation';
import { reports } from '@/lib/navigation';
import { Head } from '@inertiajs/react';

type Props = {};

export default function ReportsIndex({}: Props) {
    const { t } = useTranslation();

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
                        />
                    ))}
                </div>
            </PageContent>
        </>
    );
}
