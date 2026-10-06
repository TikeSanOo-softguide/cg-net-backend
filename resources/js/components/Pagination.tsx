import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export type PaginatedLink = {
    url: string | null;
    label: string;
    active: boolean;
    page?: number;
    disabled?: boolean;
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
    onPageChange?: (page: number) => void;
};

/**
 * Build Previous / page window / Next links matching Laravel UrlWindow.
 */
export function buildWindowedPaginationLinks(
    currentPage: number,
    lastPage: number,
    previousLabel: string,
    nextLabel: string,
    onEachSide = 3,
): PaginatedLink[] {
    const current = Math.min(Math.max(1, currentPage), Math.max(1, lastPage));
    const last = Math.max(1, lastPage);
    const links: PaginatedLink[] = [
        {
            url: null,
            label: `&laquo; ${previousLabel}`,
            active: false,
            page: Math.max(1, current - 1),
            disabled: current === 1,
        },
    ];

    for (const page of windowedPageNumbers(current, last, onEachSide)) {
        if (page === null) {
            links.push({
                url: null,
                label: '...',
                active: false,
                disabled: true,
            });
            continue;
        }

        links.push({
            url: null,
            label: String(page),
            active: current === page,
            page,
        });
    }

    links.push({
        url: null,
        label: `${nextLabel} &raquo;`,
        active: false,
        page: Math.min(last, current + 1),
        disabled: current === last,
    });

    return links;
}

/**
 * Mirror Illuminate\Pagination\UrlWindow page ranges.
 * - start: 1..10, ..., last-1, last
 * - middle: 1, 2, ..., current±3, ..., last-1, last
 * - end: 1, 2, ..., last 10 pages
 */
function windowedPageNumbers(current: number, last: number, onEachSide: number): Array<number | null> {
    if (last < onEachSide * 2 + 8) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const window = onEachSide + 4;

    if (current <= window) {
        return [
            ...range(1, window + onEachSide),
            null,
            ...range(last - 1, last),
        ];
    }

    if (current > last - window) {
        return [
            ...range(1, 2),
            null,
            ...range(last - (window + (onEachSide - 1)), last),
        ];
    }

    return [
        ...range(1, 2),
        null,
        ...range(current - onEachSide, current + onEachSide),
        null,
        ...range(last - 1, last),
    ];
}

function range(start: number, end: number): number[] {
    if (end < start) {
        return [];
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

export function Pagination({ meta, summary, onPageChange }: PaginationProps) {
    const { t } = useTranslation();

    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2.5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <p className="text-xs text-muted-foreground">{summary}</p>
            <nav className="flex flex-wrap gap-1.5" aria-label="Pagination">
                {meta.links.map((link, index) => {
                    const renderedLinkLabel = onPageChange
                        ? link.label
                        : index === 0
                          ? `&laquo; ${t('common.previous_page')}`
                          : index === meta.links.length - 1
                            ? `${t('common.next_page')} &raquo;`
                            : link.label;
                    const label = renderedLinkLabel.replace(/&laquo;|&raquo;/g, '').trim();

                    if (onPageChange && link.page !== undefined) {
                        return (
                            <Button
                                key={`${label}-${index}`}
                                type="button"
                                variant={link.active ? 'primary' : 'outline'}
                                size="sm"
                                className="min-w-8 px-2.5"
                                aria-current={link.active ? 'page' : undefined}
                                aria-label={label}
                                disabled={link.disabled}
                                onClick={() => onPageChange(link.page as number)}
                            >
                                <span dangerouslySetInnerHTML={{ __html: renderedLinkLabel }} />
                            </Button>
                        );
                    }

                    if (!link.url) {
                        return (
                            <Button
                                key={`${label}-${index}`}
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled
                                className="min-w-8 px-2.5"
                            >
                                <span dangerouslySetInnerHTML={{ __html: renderedLinkLabel }} />
                            </Button>
                        );
                    }

                    return (
                        <Button
                            key={`${label}-${index}`}
                            asChild
                            variant={link.active ? 'primary' : 'outline'}
                            size="sm"
                            className="min-w-8 px-2.5"
                        >
                            <Link href={link.url} preserveState preserveScroll>
                                <span
                                    className={cn(link.active && 'text-primary-foreground')}
                                    dangerouslySetInnerHTML={{ __html: renderedLinkLabel }}
                                />
                            </Link>
                        </Button>
                    );
                })}
            </nav>
        </div>
    );
}
