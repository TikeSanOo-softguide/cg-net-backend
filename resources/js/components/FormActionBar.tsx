import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { SaveIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type FormActionBarProps = {
    cancelHref: string;
    processing?: boolean;
    submitLabel?: string;
    children?: ReactNode;
    className?: string;
};

export function FormActionBar({ cancelHref, processing = false, submitLabel, children, className }: FormActionBarProps) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-4 mt-2 border-t border-border/70 bg-card/95 px-4 py-3 backdrop-blur-sm',
                'sm:static sm:z-0 sm:mx-0 sm:mt-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:backdrop-blur-none',
                className,
            )}
        >
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" asChild>
                    <Link href={cancelHref}>
                        <XIcon />
                        {t('common.cancel')}
                    </Link>
                </Button>
                {children ?? (
                    <Button type="submit" disabled={processing}>
                        <SaveIcon />
                        {submitLabel ?? t('common.save')}
                    </Button>
                )}
            </div>
        </div>
    );
}
