import { http } from '@inertiajs/react';

const storageKey = 'cg-admin-device-id';

function deviceId(): string {
    try {
        const existing = localStorage.getItem(storageKey)?.trim() ?? '';
        if (/^[A-Za-z0-9-]{8,64}$/.test(existing)) {
            return existing;
        }

        const created = crypto.randomUUID();
        localStorage.setItem(storageKey, created);

        return created;
    } catch {
        return crypto.randomUUID();
    }
}

function deviceHeaders(): Record<string, string> {
    return {
        'X-Device-Id': deviceId(),
        'X-Device-Name': 'Web',
        'X-Platform': 'web',
    };
}

function sameOrigin(url: string): boolean {
    return url.startsWith('/') || url.startsWith(window.location.origin);
}

export function installAdminDeviceHeaders(): void {
    const headers = deviceHeaders();

    http.onRequest((config) => {
        config.headers = {
            ...headers,
            ...config.headers,
        };

        return config;
    });

    const originalFetch = window.fetch.bind(window);

    window.fetch = (input, init) => {
        const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url;

        if (!sameOrigin(url)) {
            return originalFetch(input, init);
        }

        const nextHeaders = new Headers(init?.headers);
        Object.entries(headers).forEach(([key, value]) => {
            if (!nextHeaders.has(key)) {
                nextHeaders.set(key, value);
            }
        });

        return originalFetch(input, { ...init, headers: nextHeaders });
    };
}
