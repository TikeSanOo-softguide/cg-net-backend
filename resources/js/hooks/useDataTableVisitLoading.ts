import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

type Visit = {
    url: URL;
    method: string;
    prefetch?: boolean;
};

type TableLoadingSubscriber = (loading: boolean) => void;

const subscribers = new Map<string, TableLoadingSubscriber>();
const activeVisits = new Map<string, Set<Visit>>();
const visitOwners = new Map<Visit, string>();
let interactedTableId: string | null = null;
let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;

function startListening(): void {
    if (removeStartListener) {
        return;
    }

    removeStartListener = router.on('start', (event) => {
        const visit = event.detail.visit;

        if (
            visit.prefetch ||
            visit.method !== 'get' ||
            visit.url.pathname !== window.location.pathname ||
            interactedTableId === null ||
            !subscribers.has(interactedTableId)
        ) {
            return;
        }

        const visits = activeVisits.get(interactedTableId) ?? new Set<Visit>();
        visits.add(visit);
        activeVisits.set(interactedTableId, visits);
        visitOwners.set(visit, interactedTableId);
        subscribers.get(interactedTableId)?.(true);
    });

    removeFinishListener = router.on('finish', (event) => {
        const visit = event.detail.visit;
        const tableId = visitOwners.get(visit);

        if (!tableId) {
            return;
        }

        visitOwners.delete(visit);
        const visits = activeVisits.get(tableId);
        visits?.delete(visit);

        if (!visits?.size) {
            activeVisits.delete(tableId);
            subscribers.get(tableId)?.(false);
        }
    });
}

function stopListening(): void {
    if (subscribers.size > 0) {
        return;
    }

    removeStartListener?.();
    removeFinishListener?.();
    removeStartListener = undefined;
    removeFinishListener = undefined;
    activeVisits.clear();
    visitOwners.clear();
    interactedTableId = null;
}

export function markDataTableVisitInitiator(tableId: string): void {
    interactedTableId = tableId;
}

export function useDataTableVisitLoading(tableId: string): boolean {
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        subscribers.set(tableId, setLoading);
        startListening();

        return () => {
            subscribers.delete(tableId);
            activeVisits.delete(tableId);
            stopListening();
        };
    }, [tableId]);

    return loading;
}
