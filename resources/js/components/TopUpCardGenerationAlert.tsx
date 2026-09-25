import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

import { toast } from '@/hooks/use-toast';
import { useTranslation } from '@/hooks/useTranslation';

type GenerationStatus = {
    token: string | null;
    status: 'processing' | 'completed' | 'failed' | null;
    total_cards: number;
};

const WATCH_KEY = 'top_up_card_generation_watch';
const NOTIFIED_KEY = 'top_up_card_generation_notified';
const POLL_MS = 2000;

function readWatchToken(): string | null {
    try {
        return sessionStorage.getItem(WATCH_KEY);
    } catch {
        return null;
    }
}

function writeWatchToken(token: string | null): void {
    try {
        if (token === null) {
            sessionStorage.removeItem(WATCH_KEY);
            return;
        }

        sessionStorage.setItem(WATCH_KEY, token);
    } catch {
        // Ignore storage failures (private mode, etc.).
    }
}

function wasNotified(token: string): boolean {
    try {
        return sessionStorage.getItem(NOTIFIED_KEY) === token;
    } catch {
        return false;
    }
}

function markNotified(token: string): void {
    try {
        sessionStorage.setItem(NOTIFIED_KEY, token);
        sessionStorage.removeItem(WATCH_KEY);
    } catch {
        // Ignore storage failures.
    }
}

async function fetchGenerationStatus(): Promise<GenerationStatus | null> {
    try {
        const response = await fetch('/top-up-cards/generation-status', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        return (await response.json()) as GenerationStatus;
    } catch {
        return null;
    }
}

export function TopUpCardGenerationAlert() {
    const { t } = useTranslation();
    const page = usePage();
    const shared = (page.props as { topUpCardGeneration?: GenerationStatus | null }).topUpCardGeneration;
    const tRef = useRef(t);
    tRef.current = t;

    useEffect(() => {
        const notify = (status: GenerationStatus) => {
            if (!status.token || (status.status !== 'completed' && status.status !== 'failed')) {
                return;
            }

            const watching = readWatchToken();

            if (watching !== status.token || wasNotified(status.token)) {
                return;
            }

            markNotified(status.token);
            const translate = tRef.current;

            if (status.status === 'completed') {
                toast({
                    variant: 'success',
                    title: translate('toast.success'),
                    description: translate('top_up_cards.generated').replace(
                        ':count',
                        String(status.total_cards ?? 0),
                    ),
                });
                return;
            }

            toast({
                variant: 'error',
                title: translate('toast.error'),
                description: translate('top_up_cards.generation_failed'),
            });
        };

        if (shared?.token && shared.status === 'processing') {
            writeWatchToken(shared.token);
        }

        if (shared) {
            notify(shared);
        }

        const watching = readWatchToken();

        if (!watching && shared?.status !== 'processing') {
            return;
        }

        let cancelled = false;
        let inFlight = false;

        const poll = async () => {
            if (cancelled || inFlight || document.hidden) {
                return;
            }

            inFlight = true;
            const status = await fetchGenerationStatus();
            inFlight = false;

            if (cancelled || !status) {
                return;
            }

            if (status.token && status.status === 'processing') {
                writeWatchToken(status.token);
            }

            notify(status);
        };

        void poll();
        const intervalId = window.setInterval(() => {
            void poll();
        }, POLL_MS);

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void poll();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            window.clearInterval(intervalId);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [shared?.token, shared?.status, shared?.total_cards]);

    return null;
}
