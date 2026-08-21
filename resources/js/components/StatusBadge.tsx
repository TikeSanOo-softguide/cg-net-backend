import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

const liveStatuses = new Set(['under_review', 'good', 'active']);

function variantFor(status: string): 'default' | 'secondary' | 'outline' | 'destructive' | 'warning' {
    switch (status) {
        case 'approved':
        case 'paid':
        case 'good':
        case 'active':
            return 'default';
        case 'under_review':
            return 'warning';
        case 'suspended':
            return 'warning';
        case 'terminated':
        case 'rejected':
        case 'offline':
        case 'failed':
            return 'destructive';
        case 'slow':
        case 'expired':
        case 'unpaid':
        case 'pending':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation();
    const live = liveStatuses.has(status);

    return (
        <Badge variant={variantFor(status)} className={cn('gap-1.5', className)}>
            {live ? (
                <span className="size-1.5 rounded-full bg-current status-pulse" aria-hidden />
            ) : null}
            {t(`status.${status}`)}
        </Badge>
    );
}
