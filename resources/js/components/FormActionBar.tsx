import type { ReactNode } from 'react';
import { SaveIcon, SendIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export const formActionBarClass =
    'flex w-full shrink-0 flex-wrap items-center justify-center gap-2 border-t border-border/70 bg-[#e8eef0] px-4 py-3 sm:px-5 sm:py-4 dark:bg-[#1a2e31]';

export const formActionButtonClass =
    'h-8 w-[200px] shrink-0 rounded-[4px] border-transparent bg-[#dfe8ea] text-[#3d5054] hover:bg-primary hover:text-primary-foreground dark:bg-muted dark:text-muted-foreground dark:hover:bg-primary dark:hover:text-primary-foreground';

type FormActionBarProps = {
    onCancel?: () => void;
    processing?: boolean;
    submitLabel?: string;
    mode?: 'create' | 'edit';
    children?: ReactNode;
    className?: string;
};

export function FormActionBar({
    onCancel,
    processing = false,
    submitLabel,
    mode = 'create',
    children,
    className,
}: FormActionBarProps) {
    const { t } = useTranslation();
    const cancelLabel = t('common.cancel');
    const actionLabel = submitLabel ?? (mode === 'edit' ? t('common.update') : t('common.submit'));
    const SubmitIcon = mode === 'create' ? SendIcon : SaveIcon;

    return (
        <div className={cn(formActionBarClass, className)}>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                disabled={processing}
                className={formActionButtonClass}
                onClick={onCancel}
            >
                <XIcon className="size-3.5" strokeWidth={1.85} />
                {cancelLabel}
            </Button>
            {children ?? (
                <Button type="submit" size="sm" variant="ghost" disabled={processing} className={formActionButtonClass}>
                    <SubmitIcon className="size-3.5" strokeWidth={1.85} />
                    {actionLabel}
                </Button>
            )}
        </div>
    );
}
