import { FormEvent, type ReactNode } from 'react';

import { FormActionBar } from '@/components/FormActionBar';
import { Card, CardContent } from '@/components/ui/card';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    processing?: boolean;
    children: ReactNode;
};

export function CmsFormShell({ onSubmit, cancelHref, processing = false, children }: CmsFormShellProps) {
    return (
        <Card className="max-w-3xl gap-0 py-0">
            <CardContent className="px-4 py-4 pb-24 sm:px-5 sm:py-5 sm:pb-5">
                <form onSubmit={onSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {children}
                    <FormActionBar cancelHref={cancelHref} processing={processing} className="sm:col-span-2" />
                </form>
            </CardContent>
        </Card>
    );
}
