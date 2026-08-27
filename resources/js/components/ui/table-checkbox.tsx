import { useEffect, useRef } from 'react';
import { CheckIcon, MinusIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type TableCheckboxProps = {
    checked: boolean;
    indeterminate?: boolean;
    onChange: () => void;
    label: string;
    disabled?: boolean;
};

export function TableCheckbox({ checked, indeterminate = false, onChange, label, disabled = false }: TableCheckboxProps) {
    const ref = useRef<HTMLInputElement>(null);
    const mixed = indeterminate && ! checked;

    useEffect(() => {
        if (ref.current) {
            ref.current.indeterminate = mixed;
        }
    }, [mixed]);

    return (
        <label
            className={cn(
                'relative inline-flex size-4 shrink-0 cursor-pointer items-center justify-center',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <input
                ref={ref}
                type="checkbox"
                checked={checked}
                disabled={disabled}
                aria-label={label}
                onChange={() => onChange()}
                className="peer sr-only"
            />
            <span
                aria-hidden
                className={cn(
                    'flex size-4 items-center justify-center rounded-[4px] border transition-colors',
                    'peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40',
                    checked || mixed
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-input bg-card',
                )}
            >
                {checked ? <CheckIcon className="size-3" strokeWidth={2.6} /> : null}
                {mixed ? <MinusIcon className="size-3" strokeWidth={2.6} /> : null}
            </span>
        </label>
    );
}
