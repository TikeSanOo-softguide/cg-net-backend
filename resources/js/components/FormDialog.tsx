import type { LucideIcon } from 'lucide-react';
import { useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { XIcon } from 'lucide-react';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type FormDialogSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'full';

const sizeClasses: Record<FormDialogSize, string> = {
    sm: 'sm:w-[min(100%-2rem,420px)] sm:max-w-[420px]',
    md: 'sm:w-[min(100%-2rem,560px)] sm:max-w-[560px]',
    lg: 'sm:w-[min(100%-2rem,720px)] sm:max-w-[720px]',
    xl: 'sm:w-[min(100%-2rem,880px)] sm:max-w-[880px]',
    '2xl': 'sm:w-[min(100%-2rem,1040px)] sm:max-w-[1040px]',
    '3xl': 'sm:w-[min(100%-2rem,1200px)] sm:max-w-[1200px]',
    full: 'sm:w-[calc(100%-2rem)] sm:max-w-none',
};

type FormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    icon: LucideIcon;
    children: ReactNode;
    className?: string;
    size?: FormDialogSize;
};

export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    icon: Icon,
    children,
    className,
    size = 'md',
}: FormDialogProps) {
    const { t } = useTranslation();
    type DialogContentSnapshot = {
        children: ReactNode;
        title: string;
        description?: string;
        icon: LucideIcon;
    };
    const currentContent: DialogContentSnapshot = { children, title, description, icon: Icon };
    const [closingContent, setClosingContent] = useState<DialogContentSnapshot | null>(null);
    const lastOpenContent = useRef(currentContent);
    const wasOpen = useRef(open);

    if (open) {
        lastOpenContent.current = currentContent;
    }

    useLayoutEffect(() => {
        if (open) {
            wasOpen.current = true;
            setClosingContent(null);
        } else if (wasOpen.current) {
            wasOpen.current = false;
            setClosingContent(lastOpenContent.current);
        }
    }, [open]);

    const content = !open && closingContent ? closingContent : currentContent;

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setClosingContent(null);
        } else {
            setClosingContent(lastOpenContent.current);
        }

        onOpenChange(nextOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                overlayClassName="z-[80]"
                onAnimationEnd={(event) => {
                    if (!open && event.target === event.currentTarget) {
                        setClosingContent(null);
                    }
                }}
                className={cn(
                    'flex flex-col gap-0 overflow-hidden border-border/80 bg-[#f3f6f7] p-0 shadow-[0_8px_24px_rgb(23_50_54/0.08),0_20px_48px_rgb(23_50_54/0.12)] dark:bg-[#152628] dark:shadow-[0_8px_24px_rgb(0_0_0/0.28),0_20px_48px_rgb(0_0_0/0.32)] [&>button.absolute]:hidden',
                    'z-[81] inset-0 top-0 left-0 h-dvh max-h-dvh w-full max-w-none translate-x-0 translate-y-0 rounded-none',
                    'sm:inset-auto sm:top-[50%] sm:left-[50%] sm:h-auto sm:max-h-[min(90vh,720px)] sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-[12px]',
                    sizeClasses[size],
                    className,
                )}
            >
                <div className="flex h-full min-h-0 flex-col">
                    <div className="flex shrink-0 items-start gap-3 border-b border-border/70 bg-[#eef3f4] px-4 py-4 sm:px-5 dark:bg-[#1a2e31]">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-[8px] bg-primary/10 text-primary">
                            <content.icon className="size-[18px]" strokeWidth={1.85} />
                        </div>
                        <DialogHeader className="min-w-0 flex-1 gap-1 text-left">
                            <DialogTitle className="text-[15px] leading-snug font-semibold">
                                {content.title}
                            </DialogTitle>
                            {content.description ? (
                                <DialogDescription className="text-left text-[13px] leading-5">
                                    {content.description}
                                </DialogDescription>
                            ) : null}
                        </DialogHeader>
                        <DialogClose
                            className="inline-flex size-8 shrink-0 items-center justify-center rounded-[6px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label={t('common.close')}
                        >
                            <XIcon className="size-4" strokeWidth={1.85} />
                        </DialogClose>
                    </div>
                    <div className="flex min-h-0 flex-1 flex-col">{content.children}</div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
