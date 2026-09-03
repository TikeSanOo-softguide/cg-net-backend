import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

function dotClass(status: string): string {
    switch (status) {
        case 'approved':
        case 'paid':
        case 'good':
        case 'published':
        case 'active':
        case 'valid':
        case 'completed':
            return 'bg-emerald-500';
        case 'under_review':
        case 'draft':
        case 'pending':
            return 'bg-amber-400';
        case 'rejected':
        case 'expired':
        case 'terminated':
        case 'offline':
        case 'failed':
        case 'archived':
        case 'invalid':
            return 'bg-red-500';
        case 'suspended':
        case 'slow':
        case 'unpaid':
        case 'redeemed':
            return 'bg-orange-400';
        default:
            return 'bg-muted-foreground/45';
    }
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation();

    return (
        <span className={cn('inline-flex items-center gap-2 text-[13px] font-medium text-foreground', className)}>
            <span className={cn('size-2 shrink-0 rounded-full', dotClass(status))} aria-hidden />
            {t(`status.${status}`)}
        </span>
    );
}
