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
    return (
        <FormControl icon={SearchIcon} className={cn('w-full', className)}>
            <Input
                type="text"
                inputMode="search"
                autoComplete="off"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className={size === 'sm' ? 'h-10 text-sm' : undefined}
            />
        </FormControl>
    );
}
