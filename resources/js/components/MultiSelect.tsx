import { useState } from 'react';
import { CheckIcon, ChevronDownIcon, XIcon, type LucideIcon } from 'lucide-react';

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
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
    const [open, setOpen] = useState(false);
    const selected = options.filter((option) => values.includes(option.value));

    const toggle = (value: string) => {
        if (values.includes(value)) {
            onChange(values.filter((item) => item !== value));

            return;
        }

        onChange([...values, value]);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <FormControl icon={icon}>
                <DropdownMenuTrigger asChild>
                    <button
                        id={id}
                        type="button"
                        disabled={disabled}
                        aria-invalid={invalid}
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
                                            onPointerDown={(event) => {
                                                event.preventDefault();
                                                event.stopPropagation();
                                            }}
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
                </DropdownMenuTrigger>
            </FormControl>
            <DropdownMenuContent
                align="start"
                sideOffset={6}
                className="z-[90] max-h-64 min-w-[var(--radix-dropdown-menu-trigger-width)] w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto rounded-[6px] border-border bg-card p-1.5 shadow-card"
            >
                {options.map((option) => {
                    const checked = values.includes(option.value);

                    return (
                        <DropdownMenuItem
                            key={option.value}
                            onSelect={(event) => {
                                event.preventDefault();
                                toggle(option.value);
                            }}
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-sm',
                                checked ? 'bg-primary/10 text-primary focus:bg-primary/10 focus:text-primary' : 'text-foreground',
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
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
