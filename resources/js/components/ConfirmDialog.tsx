import { CheckIcon, CircleHelpIcon, CircleXIcon, TriangleAlertIcon, XIcon } from 'lucide-react';

import { formActionCancelClass, formActionSubmitClass } from '@/components/FormActionBar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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
    const Icon = destructive ? TriangleAlertIcon : CircleHelpIcon;
    const cancelText = cancelLabel ?? t('common.cancel');
    const confirmText = confirmLabel ?? t('common.confirm');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                overlayClassName="fixed inset-0 z-[100] h-dvh w-full bg-[#173236]/40 backdrop-blur-[4px]"
                className={cn(
                    'z-[101] gap-0 overflow-hidden rounded-[12px] border border-white/60 bg-white p-0',
                    'shadow-[0_10px_28px_rgb(23_50_54/0.16)] dark:border-white/10 dark:bg-[#152628]',
                    'dark:shadow-[0_12px_32px_rgb(0_0_0/0.45)] [&>button.absolute]:hidden',
                    'fixed top-[50%] left-[50%] w-[min(100%-2rem,340px)] max-w-[340px] translate-x-[-50%] translate-y-[-50%]',
                )}
            >
                <DialogClose
                    className="absolute top-2.5 right-2.5 z-[1] inline-flex size-7 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label={t('common.close')}
                >
                    <XIcon className="size-3.5" strokeWidth={1.9} />
                </DialogClose>
                <div className="flex items-start gap-3 px-4 pt-4 pr-11 pb-3.5">
                    <div
                        className={cn(
                            'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-[8px]',
                            destructive ? 'bg-danger/12 text-danger' : 'bg-primary/12 text-primary',
                        )}
                    >
                        <Icon className="size-4" strokeWidth={1.9} />
                    </div>
                    <DialogHeader className="min-w-0 flex-1 gap-1 text-left">
                        <DialogTitle className="font-heading text-[15px] leading-snug font-semibold text-foreground">
                            {title}
                        </DialogTitle>
                        <DialogDescription className="text-left text-[12.5px] leading-[18px] text-muted-foreground">
                            {description}
                        </DialogDescription>
                    </DialogHeader>
                </div>
                <DialogFooter className="flex-row flex-wrap items-center justify-center gap-2 border-t border-border/60 bg-[#e8eef0] px-4 py-3 sm:justify-center dark:bg-[#1a2e31]">
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        disabled={processing}
                        className={formActionCancelClass}
                        onClick={() => onOpenChange(false)}
                    >
                        <CircleXIcon className="size-3.5" strokeWidth={1.9} />
                        {cancelText}
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        disabled={processing}
                        className={formActionSubmitClass}
                        onClick={onConfirm}
                    >
                        {processing ? <Spinner size="xs" className="text-current" /> : <CheckIcon className="size-3.5" strokeWidth={1.85} />}
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
