import { BanIcon, CircleCheckIcon } from 'lucide-react';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type StaffStatusSwitchProps = {
    id?: string;
    value: string;
    onChange?: (value: string) => void;
    readOnly?: boolean;
};

export function StaffStatusSwitch({
    id,
    value,
    onChange,
    readOnly = false,
}: StaffStatusSwitchProps) {
    const { t } = useTranslation();
    const active = value === 'active';

    const toggle = () => {
        if (readOnly || ! onChange) {
            return;
        }

        onChange(active ? 'inactive' : 'active');
    };

    return (
        <div className="flex h-10 items-center gap-2">
            <BanIcon
                className={cn('size-3.5 shrink-0', active ? 'text-muted-foreground/45' : 'text-danger')}
                strokeWidth={1.9}
            />
            <span className={cn('text-[11px] leading-none', active ? 'text-muted-foreground' : 'font-semibold text-danger')}>
                {t('status.inactive')}
            </span>
            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={active}
                aria-readonly={readOnly}
                aria-label={active ? t('status.active') : t('status.inactive')}
                disabled={readOnly}
                onClick={toggle}
                className={cn(
                    'relative h-[18px] w-8 shrink-0 rounded-full transition-colors',
                    active ? 'bg-primary' : 'bg-[#d5dde2] dark:bg-muted',
                    readOnly ? 'cursor-default disabled:opacity-100' : 'cursor-pointer',
                )}
            >
                <span
                    className={cn(
                        'absolute top-[2px] size-[14px] rounded-full bg-white shadow-sm transition-[left]',
                        active ? 'left-[16px]' : 'left-[2px]',
                    )}
                />
            </button>
            <span className={cn('text-[11px] leading-none', active ? 'font-semibold text-primary' : 'text-muted-foreground')}>
                {t('status.active')}
            </span>
            <CircleCheckIcon
                className={cn('size-3.5 shrink-0', active ? 'text-primary' : 'text-muted-foreground/45')}
                strokeWidth={1.9}
            />
        </div>
    );
}
