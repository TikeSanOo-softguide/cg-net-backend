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
