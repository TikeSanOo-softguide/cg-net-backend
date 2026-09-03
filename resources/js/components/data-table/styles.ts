import { cn } from '@/lib/utils';

export function csvEscape(value: string): string {
    if (/[",\n]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }

    return value;
}

export const EDGE_PAD = 'px-4 sm:px-5';
export const EDGE_CELL = 'px-3 first:pl-3.5 last:pr-3.5';

export const headerCellClass =
    'group/head h-10 text-[13px] font-medium tracking-normal text-muted-foreground normal-case hover:bg-primary/[0.08] hover:text-primary';

export const toolbarFieldClass = cn(
    'rounded-md border-input bg-surface text-foreground shadow-none transition-colors',
    'hover:border-primary/35 hover:bg-surface',
    'focus-visible:border-primary focus-visible:bg-surface focus-visible:ring-1 focus-visible:ring-primary/25',
);

export const toolbarInputClass = cn(
    toolbarFieldClass,
    'h-8 py-0 text-[11px] placeholder:text-[11px] placeholder:text-muted-foreground/80',
);

export const toolbarSelectClass = cn(
    toolbarFieldClass,
    'h-8 py-0 text-[11px] data-[placeholder]:text-[11px] data-[placeholder]:text-muted-foreground/80',
);

export const toolbarFiltersWrapperClass = cn(
    '[&_[data-slot=select-trigger]]:h-8',
    '[&_[data-slot=select-trigger]]:rounded-md',
    '[&_[data-slot=select-trigger]]:border-input',
    '[&_[data-slot=select-trigger]]:bg-surface',
    '[&_[data-slot=select-trigger]]:py-0',
    '[&_[data-slot=select-trigger]]:text-[11px]',
    '[&_[data-slot=select-trigger]]:shadow-none',
    '[&_[data-slot=select-trigger]]:hover:border-primary/35',
    '[&_[data-slot=select-trigger]]:hover:bg-surface',
    '[&_[data-slot=select-trigger]]:focus-visible:border-primary',
    '[&_[data-slot=select-trigger]]:focus-visible:bg-surface',
    '[&_[data-slot=select-trigger]]:focus-visible:ring-1',
    '[&_[data-slot=select-trigger]]:focus-visible:ring-primary/25',
    '[&_[data-slot=select-trigger]]:data-[state=open]:border-primary',
    '[&_[data-slot=select-trigger]]:data-[state=open]:bg-surface',
    '[&_[data-slot=select-trigger]]:data-[state=open]:ring-1',
    '[&_[data-slot=select-trigger]]:data-[state=open]:ring-primary/25',
    '[&_[data-slot=select-trigger]]:data-[placeholder]:text-[11px]',
    '[&_[data-slot=select-trigger]]:data-[placeholder]:text-muted-foreground/80',
    '[&_[data-slot=input]]:h-8',
    '[&_[data-slot=input]]:rounded-md',
    '[&_[data-slot=input]]:border-input',
    '[&_[data-slot=input]]:bg-surface',
    '[&_[data-slot=input]]:py-0',
    '[&_[data-slot=input]]:text-[11px]',
    '[&_[data-slot=input]]:shadow-none',
    '[&_[data-slot=input]]:hover:border-primary/35',
    '[&_[data-slot=input]]:hover:bg-surface',
    '[&_[data-slot=input]]:focus-visible:border-primary',
    '[&_[data-slot=input]]:focus-visible:bg-surface',
    '[&_[data-slot=input]]:focus-visible:ring-1',
    '[&_[data-slot=input]]:focus-visible:ring-primary/25',
    '[&_[data-slot=input]]:data-[placeholder=true]:text-[11px]',
    '[&_[data-slot=input]]:data-[placeholder=true]:text-muted-foreground/80',
);
