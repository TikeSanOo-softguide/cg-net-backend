import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

const liveStatuses = new Set(['under_review', 'good', 'active', 'pending', 'valid']);

function variantFor(status: string): 'default' | 'secondary' | 'outline' | 'destructive' | 'warning' | 'success' {
    switch (status) {
        case 'approved':
        case 'paid':
        case 'good':
        case 'published':
        case 'active':
        case 'valid':
            return 'success';
        case 'under_review':
        case 'draft':
        case 'pending':
        case 'suspended':
            return 'warning';
        case 'expired':
        case 'terminated':
        case 'rejected':
        case 'offline':
        case 'failed':
        case 'archived':
        case 'invalid':
            return 'destructive';
        case 'redeemed':
        case 'slow':
        case 'unpaid':
        case 'inactive':
            return 'secondary';
        case 'void':
            return 'outline';
        default:
            return 'outline';
    }
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation();
    const live = liveStatuses.has(status);

    return (
        <Badge variant={variantFor(status)} className={cn('gap-1', className)}>
            {live ? <span className="size-1.5 rounded-full bg-current status-pulse" aria-hidden /> : null}
            {t(`status.${status}`)}
        </Badge>
    );
}
