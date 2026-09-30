import { router } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type BackButtonProps = {
    href?: string | null;
    fallback?: string;
    label?: string;
    className?: string;
    onClick?: () => void;
};

/**
 * Shared back control. Prefers an explicit href (usually Inertia `return_to`
 * from the server session), then falls back to a default path.
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

                router.visit(target);
            }}
        >
            <ArrowLeftIcon className="size-3.5" strokeWidth={1.9} />
            {label ?? t('common.back')}
        </Button>
    );
}
