import type { ReactNode } from 'react';
import { SaveIcon, SendIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export const formActionBarClass =
    'flex w-full shrink-0 flex-wrap items-center justify-center gap-2 border-t border-border/70 bg-[#e8eef0] px-4 py-3 sm:px-5 sm:py-4 dark:bg-[#1a2e31]';

const formActionButtonBase =
    'h-8 w-[200px] shrink-0 rounded-[6px] border-transparent transition-all duration-200 ease-out hover:shadow-sm active:scale-[0.98]';

export const formActionButtonClass = cn(
    formActionButtonBase,
    'bg-[#dfe8ea] text-[#3d5054] hover:bg-primary hover:text-primary-foreground dark:bg-muted dark:text-muted-foreground dark:hover:bg-primary dark:hover:text-primary-foreground',
);

export const formActionCancelClass = cn(
    formActionButtonBase,
    'bg-danger/12 text-danger hover:bg-danger hover:text-danger-foreground',
);

export const formActionSubmitClass = cn(
    formActionButtonBase,
    'bg-primary/12 text-primary hover:bg-primary hover:text-primary-foreground',
);

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
                className={formActionCancelClass}
                onClick={onCancel}
            >
                <XIcon className="size-3.5" strokeWidth={1.85} />
                {cancelLabel}
            </Button>
            {children ?? (
                <Button type="submit" size="sm" variant="ghost" disabled={processing} className={formActionSubmitClass}>
                    {processing ? <Spinner size="xs" className="text-current" /> : <SubmitIcon className="size-3.5" strokeWidth={1.85} />}
                    {actionLabel}
                </Button>
            )}
        </div>
    );
}
