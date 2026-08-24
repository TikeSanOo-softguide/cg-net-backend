import { cn } from '@/lib/utils';

export const formControlClass = cn(
    'w-full min-w-0 rounded-[6px] border border-input bg-surface px-3 text-sm text-foreground shadow-none outline-none transition-colors duration-200',
    'placeholder:text-muted-foreground',
    'hover:border-primary/35',
    'focus-visible:border-primary focus-visible:ring-1 focus-visible:ring-primary/40 focus-visible:ring-offset-0',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:border-input disabled:bg-muted disabled:text-muted-foreground disabled:opacity-70 disabled:hover:border-input',
    'aria-invalid:border-danger aria-invalid:hover:border-danger aria-invalid:focus-visible:border-danger aria-invalid:focus-visible:ring-danger/25',
    'group-data-[error=true]/field:border-danger group-data-[error=true]/field:hover:border-danger group-data-[error=true]/field:focus-visible:border-danger group-data-[error=true]/field:focus-visible:ring-danger/25',
);

export const formControlIconClass = cn(
    'text-muted-foreground transition-colors duration-200',
    'group-hover/control:text-primary',
    'group-focus-within/control:text-primary',
    'group-has-[[data-state=open]]/control:text-primary',
    'group-has-[:disabled]/control:text-muted-foreground',
    'group-has-[[aria-invalid=true]]/control:text-danger',
    'group-data-[error=true]/field:text-danger',
);

export const formLabelClass = cn(
    'text-foreground transition-colors duration-200',
    'group-hover/field:text-primary',
    'group-focus-within/field:text-primary',
    'group-has-[[data-state=open]]/field:text-primary',
    'group-has-[:disabled]/field:text-muted-foreground',
    'group-data-[error=true]/field:text-danger',
);
