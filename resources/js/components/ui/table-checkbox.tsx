import { useEffect, useRef } from 'react';

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

    useEffect(() => {
        if (ref.current) {
            ref.current.indeterminate = indeterminate && ! checked;
        }
    }, [checked, indeterminate]);

    return (
        <input
            ref={ref}
            type="checkbox"
            checked={checked}
            disabled={disabled}
            aria-label={label}
            onChange={() => onChange()}
            className={cn(
                'size-4 shrink-0 cursor-pointer rounded-[4px] border border-input accent-primary',
                'focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none',
                'disabled:cursor-not-allowed disabled:opacity-50',
            )}
        />
    );
}
