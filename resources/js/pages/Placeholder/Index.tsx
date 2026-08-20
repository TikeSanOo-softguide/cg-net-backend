import { Head } from '@inertiajs/react';

import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

type PlaceholderProps = {
    titleKey: string;
};

export default function PlaceholderIndex({ titleKey }: PlaceholderProps) {
    const { t } = useTranslation();
    const title = t(titleKey);

    return (
        <>
            <Head title={title} />
            <div className="flex w-full flex-col gap-6 pt-6 lg:gap-8 lg:pt-8">
                <PageHeader title={title} />
            </div>
        </>
    );
}
