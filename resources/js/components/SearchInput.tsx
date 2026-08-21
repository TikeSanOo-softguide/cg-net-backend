import { SearchIcon } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    size?: 'sm' | 'md';
};

export function SearchInput({ value, onChange, placeholder, className, size = 'md' }: SearchInputProps) {
    const compact = size === 'sm';

    return (
        <div className={cn('relative min-w-0 w-full', className)}>
            <SearchIcon
                className={cn(
                    'pointer-events-none absolute top-1/2 -translate-y-1/2 text-muted-foreground',
                    compact ? 'left-2.5 size-3.5' : 'left-3 size-4',
                )}
            />
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className={cn(
                    'rounded-[8px] bg-surface',
                    compact ? 'h-8 pr-2.5 pl-8 text-xs' : 'h-10 pr-3 pl-9 text-sm',
                )}
            />
        </div>
    );
}
