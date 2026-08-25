import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { SaveIcon, SendIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type FormActionBarProps = {
    cancelHref?: string;
    onCancel?: () => void;
    processing?: boolean;
    submitLabel?: string;
    mode?: 'create' | 'edit';
    children?: ReactNode;
    className?: string;
    variant?: 'page' | 'modal';
};

export function FormActionBar({
    cancelHref,
    onCancel,
    processing = false,
    submitLabel,
    mode = 'create',
    children,
    className,
    variant = 'page',
}: FormActionBarProps) {
    const { t } = useTranslation();
    const cancelLabel = t('common.cancel');
    const actionLabel = submitLabel ?? (
        mode === 'edit'
            ? t('common.update')
            : variant === 'modal'
              ? t('common.submit')
              : t('common.save')
    );
    const SubmitIcon = variant === 'modal' && mode === 'create' ? SendIcon : SaveIcon;

    if (variant === 'modal') {
        return (
            <div className={cn('flex w-full shrink-0 items-center justify-center gap-2 border-t border-border/70 px-4 py-3 sm:px-5 sm:py-4', className)}>
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    disabled={processing}
                    className="h-8 w-[120px] shrink-0 rounded-[4px]"
                    onClick={onCancel}
                >
                    <XIcon className="size-3.5" strokeWidth={1.85} />
                    {cancelLabel}
                </Button>
                {children ?? (
                    <Button type="submit" size="sm" variant="primary" disabled={processing} className="h-8 w-[120px] shrink-0 rounded-[4px]">
                        <SubmitIcon className="size-3.5" strokeWidth={1.85} />
                        {actionLabel}
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-5 mt-1 border-t border-border/70 bg-card/95 px-5 py-3 backdrop-blur-sm',
                'sm:static sm:z-0 sm:mx-0 sm:mt-1 sm:border-t sm:bg-transparent sm:px-0 sm:pt-5 sm:pb-0 sm:backdrop-blur-none',
                className,
            )}
        >
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        <XIcon />
                        {cancelLabel}
                    </Button>
                ) : (
                    <Button type="button" variant="outline" asChild>
                        <Link href={cancelHref ?? '/'}>
                            <XIcon />
                            {cancelLabel}
                        </Link>
                    </Button>
                )}
                {children ?? (
                    <Button type="submit" disabled={processing}>
                        <SubmitIcon />
                        {actionLabel}
                    </Button>
                )}
            </div>
        </div>
    );
}
