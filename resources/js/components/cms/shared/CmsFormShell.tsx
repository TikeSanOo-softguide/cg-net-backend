import { FormEvent, type ReactNode } from 'react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormCard } from '@/components/FormCard';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    cancelHref?: string;
    onCancel?: () => void;
    processing?: boolean;
    variant?: 'page' | 'modal';
    mode?: 'create' | 'edit';
    children: ReactNode;
};

export function CmsFormShell({
    onSubmit,
    cancelHref,
    onCancel,
    processing = false,
    variant = 'page',
    mode = 'create',
    children,
}: CmsFormShellProps) {
    if (variant === 'modal') {
        return (
            <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
                <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                    <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">{children}</div>
                </div>
                <FormActionBar variant="modal" mode={mode} onCancel={onCancel} processing={processing} />
            </form>
        );
    }

    return (
        <FormCard>
            <form onSubmit={onSubmit} className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {children}
                <FormActionBar mode={mode} cancelHref={cancelHref} onCancel={onCancel} processing={processing} className="sm:col-span-2" />
            </form>
        </FormCard>
    );
}
