import { SearchIcon, XIcon } from 'lucide-react';

import { toolbarInputClass } from '@/components/data-table/styles';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    size?: 'sm' | 'md';
};

export function SearchInput({ value, onChange, placeholder, className, size = 'md' }: SearchInputProps) {
    const { t } = useTranslation();
    const compact = size === 'sm';
    const hasValue = value.length > 0;

    return (
        <FormControl
            icon={SearchIcon}
            compact={compact}
            className={cn('w-full', className)}
            rightSlot={
                hasValue ? (
                    <button
                        type="button"
                        aria-label={t('common.clear')}
                        className="inline-flex size-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        onClick={() => onChange('')}
                    >
                        <XIcon className="size-3.5" strokeWidth={2} />
                    </button>
                ) : undefined
            }
        >
            <Input
                type="text"
                inputMode="search"
                autoComplete="off"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className={cn(toolbarInputClass, ! compact && 'h-10 text-sm placeholder:text-sm')}
            />
        </FormControl>
    );
}
