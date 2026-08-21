import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type PaginatedLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginatedLink[];
};

type PaginationProps = {
    meta: Pick<Paginated<unknown>, 'from' | 'to' | 'total' | 'links'>;
    summary: string;
};

export function Pagination({ meta, summary }: PaginationProps) {
    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[11px] text-muted-foreground">{summary}</p>
            <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                {meta.links.map((link, index) => {
                    const label = link.label.replace(/&laquo;|&raquo;/g, '').trim();

                    if (! link.url) {
                        return (
                            <Button key={`${label}-${index}`} type="button" variant="outline" size="sm" disabled className="h-7 min-w-7 px-2 text-[11px]">
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        );
                    }

                    return (
                        <Button key={`${label}-${index}`} asChild variant={link.active ? 'primary' : 'outline'} size="sm" className="h-7 min-w-7 px-2 text-[11px]">
                            <Link href={link.url} preserveState preserveScroll>
                                <span className={cn(link.active && 'text-primary-foreground')} dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Link>
                        </Button>
                    );
                })}
            </nav>
        </div>
    );
}
