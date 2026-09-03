import type { ReactNode } from 'react';
import { CircleXIcon, SaveIcon, SendIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export const formActionBarClass =
    'flex w-full shrink-0 flex-wrap items-center justify-center gap-2 border-t border-border/70 bg-[#e8eef0] px-4 py-3 sm:px-5 sm:py-4 dark:bg-[#1a2e31]';

const formActionButtonBase =
    'h-7 min-h-7 w-[148px] shrink-0 rounded-[6px] border-transparent px-2.5 text-[12px] font-medium transition-all duration-200 ease-out hover:shadow-sm active:scale-[0.98]';

export const formActionButtonClass = cn(
    formActionButtonBase,
    'bg-[#dfe8ea] text-[#3d5054] hover:bg-primary hover:text-primary-foreground dark:bg-muted dark:text-muted-foreground dark:hover:bg-primary dark:hover:text-primary-foreground',
);

export const formActionCancelClass = cn(
    formActionButtonBase,
    'border-transparent bg-danger text-danger-foreground hover:bg-[color-mix(in_srgb,var(--danger)_88%,black)]',
);

export const formActionSubmitClass = cn(
    formActionButtonBase,
    'border-transparent bg-primary text-primary-foreground hover:bg-[color-mix(in_srgb,hsl(var(--primary))_88%,black)]',
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
                variant="destructive"
                disabled={processing}
                className={formActionCancelClass}
                onClick={onCancel}
            >
                <CircleXIcon className="size-3.5" strokeWidth={1.9} />
                {cancelLabel}
            </Button>
            {children ?? (
                <Button type="submit" size="sm" variant="primary" disabled={processing} className={formActionSubmitClass}>
                    {processing ? <Spinner size="xs" className="text-current" /> : <SubmitIcon className="size-3.5" strokeWidth={1.85} />}
                    {actionLabel}
                </Button>
            )}
        </div>
    );
}
