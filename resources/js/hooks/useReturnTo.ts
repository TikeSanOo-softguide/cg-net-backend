import { usePage } from '@inertiajs/react';

type ReturnToProps = {
    return_to?: string | null;
};

/**
 * Read the server-provided return URL for the current page's Back button.
 */
export function useReturnTo(fallback: string): string {
    const page = usePage<ReturnToProps>();

    return page.props.return_to || fallback;
}
