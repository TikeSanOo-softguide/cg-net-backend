import { CheckIcon, CircleAlertIcon, CircleHelpIcon, XIcon } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    processing?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    destructive = false,
    processing = false,
    onConfirm,
}: ConfirmDialogProps) {
    const { t } = useTranslation();
    const Icon = destructive ? CircleAlertIcon : CircleHelpIcon;
    const cancelText = cancelLabel ?? t('common.cancel');
    const confirmText = confirmLabel ?? t('common.confirm');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[min(100%-2rem,400px)] gap-0 rounded-[12px] border border-border/80 bg-[#FFFFFF] p-0 shadow-[0_8px_24px_rgb(23_50_54/0.08),0_20px_48px_rgb(23_50_54/0.12)] sm:max-w-[400px] dark:bg-card dark:shadow-[0_8px_24px_rgb(0_0_0/0.28),0_20px_48px_rgb(0_0_0/0.32)] [&>button.absolute]:hidden">
                <div className="flex flex-col gap-4 px-5 py-5">
                    <div className="flex items-center gap-3">
                        <div
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-[8px]',
                                destructive ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary',
                            )}
                        >
                            <Icon className="size-[18px]" strokeWidth={1.85} />
                        </div>
                        <DialogHeader className="min-w-0 flex-1 gap-0 text-left">
                            <DialogTitle className="truncate text-[15px] leading-snug font-semibold">{title}</DialogTitle>
                        </DialogHeader>
                        <DialogClose
                            className="inline-flex size-8 shrink-0 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label={t('common.close')}
                        >
                            <XIcon className="size-4" strokeWidth={1.85} />
                        </DialogClose>
                    </div>
                    <DialogDescription className="text-left text-[13px] leading-5">{description}</DialogDescription>
                    <DialogFooter className="flex-row items-center justify-end gap-2 sm:justify-end">
                        <ActionButton
                            label={cancelText}
                            icon={XIcon}
                            className="bg-muted-foreground/12 text-muted-foreground hover:bg-muted-foreground/20 hover:text-muted-foreground"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        />
                        <ActionButton
                            label={confirmText}
                            icon={CheckIcon}
                            className="bg-primary/12 text-primary hover:bg-primary/20 hover:text-primary"
                            disabled={processing}
                            onClick={onConfirm}
                        />
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ActionButton({
    label,
    icon: Icon,
    className,
    disabled,
    onClick,
}: {
    label: string;
    icon: LucideIcon;
    className?: string;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            className={cn(
                'inline-flex h-9 items-center justify-center gap-1.5 rounded-[8px] px-3.5 text-[13px] font-medium leading-none transition-colors duration-200',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                className,
            )}
            onClick={onClick}
        >
            <Icon className="size-3.5" strokeWidth={1.85} />
            {label}
        </button>
    );
}
