import { FormEvent, type ReactNode } from 'react';

import { FormActionBar } from '@/components/FormActionBar';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    processing?: boolean;
    mode?: 'create' | 'edit';
    children: ReactNode;
};

export function CmsFormShell({
    onSubmit,
    onCancel,
    processing = false,
    mode = 'create',
    children,
}: CmsFormShellProps) {
    return (
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">{children}</div>
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={processing} />
        </form>
    );
}
