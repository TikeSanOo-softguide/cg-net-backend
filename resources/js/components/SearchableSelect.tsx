import * as React from 'react';
import { CheckIcon, ChevronDownIcon, ChevronUpIcon, SearchIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    value: string;
    label: string;
};

type SearchableSelectProps = {
    value: string;
    onValueChange: (value: string) => void;
    options: SearchableSelectOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    className?: string;
    disabled?: boolean;
};

function SearchableSelect({
    value,
    onValueChange,
    options,
    placeholder = 'Select...',
    searchPlaceholder = 'Search...',
    className,
    disabled = false,
}: SearchableSelectProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const containerRef = React.useRef<HTMLDivElement>(null);
    const searchRef = React.useRef<HTMLInputElement>(null);
    const selectedOption = options.find((option) => option.value === value);
    const filteredOptions = React.useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return options;
        }
        return options.filter((option) => option.label.toLowerCase().includes(keyword));
    }, [options, search]);

    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setOpen(false);
                setSearch('');
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    React.useEffect(() => {
        if (open) {
            requestAnimationFrame(() => {
                searchRef.current?.focus();
            });
        }
    }, [open]);

    const handleOpen = () => {
        if (disabled) {
            return;
        }
        setOpen((current) => !current);
    };

    const handleSelect = (optionValue: string) => {
        onValueChange(optionValue);
        setOpen(false);
        setSearch('');
    };

    return (
        <div ref={containerRef} className={cn('relative w-full', className)}>
            <button
                type="button"
                disabled={disabled}
                onClick={handleOpen}
                className={cn(
                    'group flex h-8 w-full min-w-0 items-center justify-between gap-2',
                    'rounded-[8px] border border-input bg-surface',
                    'px-3 py-2 text-sm text-foreground whitespace-nowrap',
                    'shadow-none outline-none transition-colors duration-200',
                    'hover:border-primary/35',
                    'focus-visible:border-primary',
                    'focus-visible:ring-1',
                    'focus-visible:ring-primary/40',
                    'focus-visible:ring-offset-0',
                    open && 'border-primary ring-1 ring-primary/40',
                    'disabled:cursor-not-allowed',
                    'disabled:border-input',
                    'disabled:bg-muted',
                    'disabled:opacity-70',
                    'disabled:hover:border-input',
                    '[&_svg]:pointer-events-none',
                    '[&_svg]:size-4',
                    '[&_svg]:shrink-0',
                    '[&_svg]:text-muted-foreground',
                    '[&_svg]:transition-colors',
                    open && '[&_svg]:text-primary',
                )}
            >
                <span className={cn('min-w-0 truncate', !selectedOption && 'text-muted-foreground')}>
                    {selectedOption?.label ?? placeholder}
                </span>

                {open ? <ChevronUpIcon className="size-4" /> : <ChevronDownIcon className="size-4" />}
            </button>

            {open && (
                <div
                    className={cn(
                        'absolute left-0 top-full z-[90] mt-1',
                        'w-full min-w-32',
                        'overflow-hidden rounded-[6px]',
                        'border border-white/40',
                        'bg-[rgba(255,255,255,0.74)]',
                        'text-popover-foreground',
                        'shadow-[0_18px_40px_rgba(15,23,42,0.16)]',
                        'backdrop-blur-xl',
                        'dark:border-white/12',
                        'dark:bg-[rgba(18,28,30,0.82)]',
                        'dark:shadow-[0_18px_45px_rgba(0,0,0,0.4)]',
                        'animate-in fade-in-0 zoom-in-95',
                    )}
                >
                    <div className="border-b border-border p-2">
                        <div className="relative">
                            <SearchIcon
                                className={cn(
                                    'pointer-events-none absolute',
                                    'left-2.5 top-1/2',
                                    '-translate-y-1/2',
                                    'size-4 text-muted-foreground',
                                )}
                            />

                            <input
                                ref={searchRef}
                                type="text"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={searchPlaceholder}
                                className={cn(
                                    'h-8 w-full rounded-[6px]',
                                    'border border-input',
                                    'bg-surface',
                                    'pl-8 pr-2',
                                    'text-sm text-foreground',
                                    'outline-none',
                                    'placeholder:text-muted-foreground',
                                    'transition-colors',

                                    'focus:border-primary',
                                    'focus:ring-1',
                                    'focus:ring-primary/40',
                                )}
                                onClick={(event) => event.stopPropagation()}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setOpen(false);
                                        setSearch('');
                                    }
                                }}
                            />
                        </div>
                    </div>

                    <div className="max-h-60 overflow-y-auto p-1">
                        {filteredOptions.length > 8 && (
                            <div className="flex items-center justify-center py-1 text-muted-foreground">
                                <ChevronUpIcon className="size-4" />
                            </div>
                        )}

                        {filteredOptions.length === 0 ? (
                            <div className="px-2 py-6 text-center text-sm text-muted-foreground">No results found.</div>
                        ) : (
                            filteredOptions.map((option) => {
                                const isSelected = option.value === value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => handleSelect(option.value)}
                                        className={cn(
                                            'relative flex w-full',
                                            'cursor-default',
                                            'items-center gap-2',
                                            'rounded-[6px]',
                                            'py-1.5 pr-8 pl-2',
                                            'text-sm text-foreground',
                                            'outline-hidden',
                                            'select-none',
                                            'transition-colors',
                                            'hover:bg-accent',
                                            'hover:text-accent-foreground',

                                            isSelected && 'bg-accent text-accent-foreground',
                                        )}
                                    >
                                        <span className="min-w-0 truncate">{option.label}</span>

                                        <span className="absolute right-2 flex size-3.5 items-center justify-center">
                                            {isSelected && <CheckIcon className="size-4" />}
                                        </span>
                                    </button>
                                );
                            })
                        )}

                        {filteredOptions.length > 8 && (
                            <div className="flex items-center justify-center py-1 text-muted-foreground">
                                <ChevronDownIcon className="size-4" />
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export { SearchableSelect };
