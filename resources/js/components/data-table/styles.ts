export function csvEscape(value: string): string {
    if (/[",\n]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }

    return value;
}

export const EDGE_PAD = 'px-4 sm:px-5';
export const EDGE_CELL = 'px-3 first:pl-3.5 last:pr-3.5';

export const headerCellClass =
    'group/head h-8 text-[11px] font-medium tracking-normal text-muted-foreground normal-case hover:bg-primary/[0.08] hover:text-primary';
