import { useEffect, type Dispatch, type SetStateAction } from 'react';
import { router, usePage } from '@inertiajs/react';

type RequestWithId = {
    id: number;
};

export function useOpenRequestFromQuery<T extends RequestWithId>(
    requests: T[],
    setSelectedRequest: Dispatch<SetStateAction<T | null>>,
) {
    const { url } = usePage();

    useEffect(() => {
        const requestId = new URL(url, window.location.origin).searchParams.get('open_request');
        const request = requests.find((item) => String(item.id) === requestId);

        if (request) {
            setSelectedRequest(request);
        }
    }, [requests, setSelectedRequest, url]);

    return () => {
        setSelectedRequest(null);

        const currentUrl = new URL(window.location.href);

        if (!currentUrl.searchParams.has('open_request')) {
            return;
        }

        currentUrl.searchParams.delete('open_request');
        router.visit(`${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
}
