import { FormEvent, type ReactNode } from 'react';

import { FormActionBar } from '@/components/FormActionBar';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    processing?: boolean;
    mode?: 'create' | 'edit';
    createdAt?: string | null;
    updatedAt?: string | null;
    children: ReactNode;
};

export function CmsFormShell({
    onSubmit,
    onCancel,
    processing = false,
    mode = 'create',
    createdAt,
    updatedAt,
    children,
}: CmsFormShellProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">{children}</div>
                {createdAt || updatedAt ? (
                    <div className="mt-5 flex flex-wrap gap-x-6 gap-y-2 pt-3 text-xs text-muted-foreground">
                        {createdAt ? (
                            <p>
                                <span className="font-medium text-foreground">{t('common.created_at')}: </span>
                                {formatDateTime(createdAt)}
                            </p>
                        ) : null}
                        {updatedAt ? (
                            <p>
                                <span className="font-medium text-foreground">{t('common.updated_at')}: </span>
                                {formatDateTime(updatedAt)}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>
            <FormActionBar mode={mode} onCancel={onCancel} processing={processing} />
        </form>
    );
}
