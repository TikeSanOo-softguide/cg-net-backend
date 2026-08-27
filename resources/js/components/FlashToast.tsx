import { useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';

import { toast } from '@/hooks/use-toast';
import { useTranslation } from '@/hooks/useTranslation';
import { translateFlash } from '@/lib/flash';

let didShowInitialFlash = false;
let lastShownFlashToken: string | null = null;

type FlashBag = {
    success?: string | null;
    error?: string | null;
    count?: number | null;
    token?: string | null;
};

type PageErrors = {
    delete?: string;
};

function flashToken(flash?: FlashBag, errors?: PageErrors): string | null {
    if (flash?.token) {
        return flash.token;
    }

    if (errors?.delete) {
        return `error:delete:${errors.delete}`;
    }

    return null;
}

export function FlashToast() {
    const { t } = useTranslation();
    const page = usePage();
    const tRef = useRef(t);
    const initialProps = useRef(page.props);
    tRef.current = t;

    useEffect(() => {
        const show = (flash?: FlashBag, errors?: PageErrors) => {
            const token = flashToken(flash, errors);

            if (! token || token === lastShownFlashToken) {
                return;
            }

            const translate = tRef.current;
            const success = translateFlash(translate, flash?.success, flash?.count);
            const error = flash?.error
                ? translateFlash(translate, flash.error)
                : errors?.delete
                    ? translate(errors.delete)
                    : null;

            if (! success && ! error) {
                return;
            }

            lastShownFlashToken = token;

            if (success) {
                toast({
                    variant: 'success',
                    title: translate('toast.success'),
                    description: success,
                });
            }

            if (error) {
                toast({
                    variant: 'error',
                    title: translate('toast.error'),
                    description: error,
                });
            }
        };

        if (! didShowInitialFlash) {
            didShowInitialFlash = true;
            const props = initialProps.current as { flash?: FlashBag; errors?: PageErrors };
            show(props.flash, props.errors);
        }

        const offSuccess = router.on('success', (event) => {
            const props = event.detail.page.props as { flash?: FlashBag; errors?: PageErrors };
            show(props.flash, props.errors);
        });

        const offError = router.on('error', (event) => {
            show(undefined, event.detail.errors as PageErrors);
        });

        return () => {
            offSuccess();
            offError();
        };
    }, []);

    return null;
}
