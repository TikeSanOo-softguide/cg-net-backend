import { PhoneIcon } from 'lucide-react';

import { CountryFlag } from '@/components/customer/CountryFlag';
import { parsePhone } from '@/lib/phone';
import { cn } from '@/lib/utils';

type PhoneDisplayProps = {
    phone: string | null | undefined;
    className?: string;
};

export function PhoneDisplay({ phone, className }: PhoneDisplayProps) {
    const parsed = parsePhone(phone);

    return (
        <span className={cn('flex min-w-0 items-center gap-2', className)}>
            {parsed.country !== 'unknown' ? (
                <CountryFlag country={parsed.country} />
            ) : (
                <span
                    className="inline-flex h-4 w-5 shrink-0 items-center justify-center rounded-[4px] bg-muted shadow-[inset_0_0_0_1px_rgba(15,23,42,0.12)]"
                    aria-hidden
                >
                    <PhoneIcon className="size-2.5 text-muted-foreground" strokeWidth={2} />
                </span>
            )}
            <span className="truncate font-medium tabular-nums text-card-foreground">{parsed.local || '—'}</span>
        </span>
    );
}
