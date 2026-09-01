import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/useTranslation';

export function VisitSpinner() {
    const { t } = useTranslation();
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let timer = 0;

        const show = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => setVisible(true), 160);
        };

        const hide = () => {
            window.clearTimeout(timer);
            setVisible(false);
        };

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit?.prefetch || visit?.preserveState) {
                return;
            }

            show();
        });
        const offFinish = router.on('finish', hide);

        return () => {
            window.clearTimeout(timer);
            offStart();
            offFinish();
        };
    }, []);

    if (! visible) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-[80] flex items-center justify-center bg-[#173236]/28 backdrop-blur-[3px] dark:bg-black/40"
            role="status"
            aria-live="polite"
            aria-label={t('common.loading')}
        >
            <div className="flex flex-col items-center gap-3 rounded-[16px] border border-primary/15 bg-card/90 px-8 py-7 shadow-[0_16px_40px_rgb(23_50_54/0.18)] dark:bg-[#152628]/92 dark:shadow-[0_16px_40px_rgb(0_0_0/0.4)]">
                <Spinner size="lg" className="text-primary" label={t('common.loading')} />
                <p className="text-[12px] font-medium tracking-wide text-muted-foreground">{t('common.please_wait')}</p>
            </div>
        </div>
    );
}
