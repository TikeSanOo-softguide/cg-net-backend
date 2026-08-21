import * as React from 'react';

import { formControlClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn('flex min-h-20 field-sizing-content py-2', formControlClass, className)}
            {...props}
        />
    );
}

export { Textarea };
