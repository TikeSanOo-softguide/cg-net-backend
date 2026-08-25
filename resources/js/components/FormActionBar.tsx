import type { ReactNode } from 'react';
import { SaveIcon, SendIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

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
