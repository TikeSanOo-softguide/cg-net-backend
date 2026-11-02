import { useEffect, useRef, type Dispatch, type SetStateAction } from 'react';
import { router, usePage } from '@inertiajs/react';

type RequestWithId = {
    id: number;
    notification_unread: boolean;
};

export function useOpenRequestFromQuery<T extends RequestWithId>(
    requests: T[],
    selectedRequest: T | null,
    setSelectedRequest: Dispatch<SetStateAction<T | null>>,
    notificationType: string,
) {
    const { url } = usePage();
    const markingReadRequestIds = useRef(new Set<number>());

    useEffect(() => {
        const requestId = new URL(url, window.location.origin).searchParams.get('open_request');
        const request = requests.find((item) => String(item.id) === requestId);

        if (request) {
            setSelectedRequest(request);
        }
    }, [requests, setSelectedRequest, url]);

    useEffect(() => {
        if (
            !selectedRequest ||
            !selectedRequest.notification_unread ||
            markingReadRequestIds.current.has(selectedRequest.id)
        ) {
            return;
        }

        const requestId = selectedRequest.id;
        markingReadRequestIds.current.add(requestId);
        router.put(
            `/dashboard/notifications/reference/${notificationType}/${requestId}/read`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => markingReadRequestIds.current.delete(requestId),
            },
        );
    }, [notificationType, selectedRequest?.id, selectedRequest?.notification_unread]);

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
