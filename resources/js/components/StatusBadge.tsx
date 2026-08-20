import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';

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
        case 'rejected':
        case 'offline':
            return 'destructive';
        case 'slow':
        case 'expired':
        case 'unpaid':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();
    const live = liveStatuses.has(status);

    return (
        <Badge variant={variantFor(status)} className="gap-1.5">
            {live ? (
                <span className="size-1.5 rounded-full bg-current status-pulse" aria-hidden />
            ) : null}
            {t(`status.${status}`)}
        </Badge>
    );
}
