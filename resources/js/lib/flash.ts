export function translateFlash(
    t: (key: string) => string,
    key: string | null | undefined,
    count?: number | null,
): string | null {
    if (! key) {
        return null;
    }

    const message = t(key);

    return count == null ? message : message.replaceAll(':count', String(count));
}

export function backendErrorMessages(errors?: Record<string, unknown>): string[] {
    if (! errors) {
        return [];
    }

    const messages: string[] = [];

    Object.values(errors).forEach((value) => {
        if (typeof value === 'string' && value.trim() !== '') {
            messages.push(value);
            return;
        }

        if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim() !== '') {
            messages.push(value[0]);
        }
    });

    return [...new Set(messages)];
}

export function firstBackendError(
    t: (key: string) => string,
    errors?: Record<string, unknown>,
): string | null {
    const [message] = backendErrorMessages(errors);

    return translateFlash(t, message);
}
