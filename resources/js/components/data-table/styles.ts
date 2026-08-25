export function csvEscape(value: string): string {
    if (/[",\n]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }

    return value;
}

export const EDGE_PAD = 'px-5 sm:px-[45px]';
export const EDGE_CELL = 'first:pl-5 last:pr-5 sm:first:pl-[45px] sm:last:pr-[45px]';

export const headerCellClass =
    'group/head h-[45px] bg-[#FFFFFF] text-muted-foreground transition-colors hover:bg-primary/[0.08] hover:text-primary dark:bg-card';
