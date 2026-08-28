import { useState } from 'react';
import { CheckIcon, ChevronDownIcon, PlusIcon, XIcon, type LucideIcon } from 'lucide-react';

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { FormControl } from '@/components/ui/form-control';
import { formControlClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

export type MultiSelectOption = {
    value: string;
    label: string;
    description?: string;
    icon?: LucideIcon;
};

type MultiSelectProps = {
    id?: string;
    values: string[];
    options: MultiSelectOption[];
    onChange: (values: string[]) => void;
    placeholder: string;
    heading?: string;
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
    heading,
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
                        className={cn(
                            formControlClass,
                            'relative flex min-h-10 h-auto items-center py-1.5 text-left',
                            icon && 'pl-10',
                        )}
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
                className="z-[90] w-[min(calc(100vw-2rem),var(--radix-dropdown-menu-trigger-width))] min-w-[min(100%,var(--radix-dropdown-menu-trigger-width))] max-h-[min(50vh,280px)] overflow-y-auto overflow-x-hidden rounded-[12px] border-border/80 bg-[#eef1f3] p-2 shadow-[0_10px_28px_rgb(23_50_54/0.12)] dark:bg-[#1a2e31]"
            >
                {heading ? (
                    <p className="mb-1.5 px-0.5 text-[11px] font-medium text-muted-foreground">{heading}</p>
                ) : null}
                <div className="flex flex-col gap-1.5">
                    {options.map((option) => {
                        const checked = values.includes(option.value);
                        const Icon = option.icon;

                        return (
                            <DropdownMenuItem
                                key={option.value}
                                onSelect={(event) => {
                                    event.preventDefault();
                                    toggle(option.value);
                                }}
                                className={cn(
                                    'flex h-auto min-h-0 w-full cursor-pointer items-center gap-2.5 rounded-[10px] border px-2 py-2 text-left shadow-none',
                                    'focus:text-foreground',
                                    checked
                                        ? 'border-primary/45 bg-primary/[0.08] focus:bg-primary/[0.08] dark:bg-primary/15'
                                        : 'border-border/70 bg-white hover:border-primary/30 hover:bg-white focus:bg-white dark:bg-card dark:focus:bg-card',
                                )}
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-[8px] bg-primary/12 text-primary">
                                    {Icon ? <Icon className="size-3.5" strokeWidth={1.85} /> : null}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[12px] font-semibold leading-[1.75] text-primary">
                                        {option.label}
                                    </span>
                                    {option.description ? (
                                        <span className="mt-px block truncate text-[10px] leading-3.5 text-muted-foreground">
                                            {option.description}
                                        </span>
                                    ) : null}
                                </span>
                                {checked ? (
                                    <CheckIcon className="size-3.5 shrink-0 text-primary" strokeWidth={2.4} />
                                ) : (
                                    <PlusIcon className="size-3.5 shrink-0 text-primary" strokeWidth={2} />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
