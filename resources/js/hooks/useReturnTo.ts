import { usePage } from '@inertiajs/react';

type ReturnToProps = {
    return_to?: string | null;
};

/**
 * Previous screen in the session navigation trail, when this page was opened from another screen.
 */
export function useReturnTo(fallback: string): string {
    const page = usePage<ReturnToProps>();

    return page.props.return_to || fallback;
}
