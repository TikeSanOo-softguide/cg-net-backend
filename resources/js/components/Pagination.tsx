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
