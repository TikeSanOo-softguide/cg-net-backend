import { Head } from '@inertiajs/react';

import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type PlaceholderProps = {
    titleKey: string;
};

export default function PlaceholderIndex({ titleKey }: PlaceholderProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t(titleKey)} />
            <PageContent>
                <PageHeader />
            </PageContent>
        </>
    );
}
