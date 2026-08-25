import { FormEvent, type ReactNode } from 'react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormCard } from '@/components/FormCard';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    processing?: boolean;
    children: ReactNode;
};

export function CmsFormShell({ onSubmit, cancelHref, processing = false, children }: CmsFormShellProps) {
    return (
        <FormCard>
            <form onSubmit={onSubmit} className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {children}
                <FormActionBar cancelHref={cancelHref} processing={processing} className="sm:col-span-2" />
            </form>
        </FormCard>
    );
}
