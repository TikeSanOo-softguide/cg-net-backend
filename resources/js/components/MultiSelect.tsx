import { useEffect, useId, useRef, useState } from 'react';
import { CheckIcon, ChevronDownIcon, XIcon, type LucideIcon } from 'lucide-react';

import { FormControl } from '@/components/ui/form-control';
import { formControlClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type MultiSelectOption = {
    value: string;
    label: string;
};

type MultiSelectProps = {
    id?: string;
    values: string[];
    options: MultiSelectOption[];
    onChange: (values: string[]) => void;
    placeholder: string;
    icon?: LucideIcon;
    invalid?: boolean;
    disabled?: boolean;
};

export function MultiSelect({
    id,
    values,
    options,
    onChange,
    placeholder,
    icon,
    invalid = false,
    disabled = false,
}: MultiSelectProps) {
    const listId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const selected = options.filter((option) => values.includes(option.value));

    useEffect(() => {
        if (! open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (! rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    const toggle = (value: string) => {
        if (values.includes(value)) {
            onChange(values.filter((item) => item !== value));

            return;
        }

        onChange([...values, value]);
    };

    return (
        <div ref={rootRef} className="relative">
            <FormControl icon={icon}>
                <button
                    id={id}
                    type="button"
                    disabled={disabled}
                    aria-expanded={open}
                    aria-controls={listId}
                    aria-invalid={invalid}
                    onClick={() => setOpen((current) => ! current)}
                    className={cn(formControlClass, 'relative flex min-h-10 h-auto items-center py-1.5 text-left')}
                >
                    <span className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 pr-8">
                        {selected.length === 0 ? (
                            <span className="text-muted-foreground">{placeholder}</span>
                        ) : (
                            selected.map((option) => (
                                <span
                                    key={option.value}
                                    className="inline-flex max-w-full items-center gap-1 rounded-[6px] bg-primary/12 px-2 py-0.5 text-xs font-medium text-primary"
                                >
                                    <span className="truncate">{option.label}</span>
                                    <span
                                        role="button"
                                        tabIndex={-1}
                                        className="rounded-full p-0.5 hover:bg-primary/15"
                                        onClick={(event) => {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            toggle(option.value);
                                        }}
                                    >
                                        <XIcon className="size-3" />
                                    </span>
                                </span>
                            ))
                        )}
                    </span>
                    <ChevronDownIcon
                        className={cn(
                            'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground transition-transform',
                            open && 'rotate-180 text-primary',
                        )}
                    />
                </button>
            </FormControl>
            {open ? (
                <ul
                    id={listId}
                    className="absolute z-20 mt-1.5 max-h-64 w-full overflow-y-auto rounded-[6px] border border-border bg-card p-1.5 shadow-card"
                >
                    {options.map((option) => {
                        const checked = values.includes(option.value);

                        return (
                            <li key={option.value}>
                                <button
                                    type="button"
                                    onClick={() => toggle(option.value)}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-sm transition-colors',
                                        checked ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-primary/8',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-4 shrink-0 items-center justify-center rounded-[4px] border transition-colors',
                                            checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-surface',
                                        )}
                                    >
                                        {checked ? <CheckIcon className="size-3" strokeWidth={2.4} /> : null}
                                    </span>
                                    <span className="truncate">{option.label}</span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
        </div>
    );
}
