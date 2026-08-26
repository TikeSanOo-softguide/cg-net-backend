import { SearchIcon } from 'lucide-react';

import { FormControl } from '@/components/ui/form-control';
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
        <FormControl icon={SearchIcon} compact={compact} className={cn('w-full', className)}>
            <Input
                type="text"
                inputMode="search"
                autoComplete="off"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className={compact ? 'h-8 rounded-[4px] bg-muted/50 py-0 text-[11px] placeholder:text-[11px]' : undefined}
            />
        </FormControl>
    );
}
