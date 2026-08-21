import { FormEvent, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { SaveIcon, XIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

type CmsFormShellProps = {
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    processing?: boolean;
    children: ReactNode;
};

export function CmsFormShell({ onSubmit, cancelHref, processing = false, children }: CmsFormShellProps) {
    const { t } = useTranslation();

    return (
        <Card className="max-w-3xl gap-0 py-0">
            <CardContent className="px-4 py-4 sm:px-5 sm:py-5">
                <form onSubmit={onSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {children}
                    <div className="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" asChild>
                            <Link href={cancelHref}>
                                <XIcon />
                                {t('common.cancel')}
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <SaveIcon />
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
