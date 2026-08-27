import { CheckIcon, CircleAlertIcon, CircleHelpIcon, Trash2Icon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
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
    const ConfirmIcon = destructive ? Trash2Icon : CheckIcon;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                overlayClassName="fixed inset-0 z-[100] h-dvh w-full bg-[#173236]/45 backdrop-blur-[2px]"
                className={cn(
                    'z-[101] gap-0 overflow-hidden rounded-[14px] border border-border/70 bg-[#f3f6f7] p-0 shadow-[0_16px_40px_rgb(23_50_54/0.18)] dark:bg-[#152628] dark:shadow-[0_16px_40px_rgb(0_0_0/0.4)] [&>button.absolute]:hidden',
                    'fixed top-[50%] left-[50%] w-[min(100%-2rem,380px)] max-w-[380px] translate-x-[-50%] translate-y-[-50%]',
                )}
            >
                <div className="flex flex-col gap-3 px-5 pt-5 pb-4">
                    <div className="flex items-start gap-3">
                        <div
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-full',
                                destructive ? 'bg-danger/12 text-danger' : 'bg-primary/12 text-primary',
                            )}
                        >
                            <Icon className="size-4" strokeWidth={1.9} />
                        </div>
                        <DialogHeader className="min-w-0 flex-1 gap-1.5 text-left">
                            <DialogTitle className="text-[15px] leading-snug font-semibold text-foreground">{title}</DialogTitle>
                            <DialogDescription className="text-left text-[12px] leading-5 text-muted-foreground">
                                {description}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogClose
                            className="inline-flex size-7 shrink-0 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-black/5 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:hover:bg-white/8"
                            aria-label={t('common.close')}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.9} />
                        </DialogClose>
                    </div>
                </div>
                <DialogFooter className="flex-row items-center justify-center gap-2 border-t border-border/70 bg-[#e8eef0] px-5 py-3 sm:justify-center dark:bg-[#1a2e31]">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={processing}
                        className="h-7 min-h-7 w-[100px] rounded-[6px] border-transparent bg-[#d4dce0] px-2.5 text-[11px] text-[#3d5054] hover:bg-[#c5d0d4] hover:text-[#3d5054] dark:bg-muted dark:text-muted-foreground dark:hover:bg-muted/80"
                        onClick={() => onOpenChange(false)}
                    >
                        <XIcon className="size-3" strokeWidth={2} />
                        {cancelText}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'primary'}
                        size="sm"
                        disabled={processing}
                        className="h-7 min-h-7 w-[100px] rounded-[6px] px-2.5 text-[11px]"
                        onClick={onConfirm}
                    >
                        <ConfirmIcon className="size-3" strokeWidth={2} />
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
