import { router } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { navigationPopHeaders } from '@/lib/navigation-stack';
import { cn } from '@/lib/utils';

type BackButtonProps = {
    href?: string | null;
    fallback?: string;
    label?: string;
    className?: string;
    onClick?: () => void;
};

/**
 * Shared back control. Visits the previous screen in the session trail and
 * pops that screen. Callers that pass onClick own the visit and should send
 * navigationPopHeaders when they leave the current screen.
 */
export function BackButton({ href, fallback = '/', label, className, onClick }: BackButtonProps) {
    const { t } = useTranslation();
    const target = href || fallback;

    return (
        <Button
            type="button"
            size="sm"
            className={cn('shrink-0 gap-1.5', className)}
            onClick={() => {
                if (onClick) {
                    onClick();
                    return;
                }

                router.visit(target, { headers: navigationPopHeaders });
            }}
        >
            <ArrowLeftIcon className="size-3.5" strokeWidth={1.9} />
            {label ?? t('common.back')}
        </Button>
    );
}
