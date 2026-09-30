<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Session-backed return URLs for reusable "Back" navigation.
 *
 * Source pages call {@see remember()} while they are viewed so a later detail
 * page can send the user back. Destination pages call {@see captureReferer()}
 * so deep links from other screens (e.g. ledger health → transactions) also work,
 * then pass {@see get()} to the Inertia page as `return_to`.
 */
final class ReturnTo
{
    private const SESSION_PREFIX = 'navigation.return_to.';

    /**
     * Store the current request URL as the return target for a destination key.
     */
    public static function remember(Request $request, string $destinationKey): void
    {
        $url = self::sanitize($request->fullUrl());

        if ($url !== null) {
            $request->session()->put(self::sessionKey($destinationKey), $url);
        }
    }

    /**
     * If the request has an internal Referer that isn't the destination itself,
     * store it as the return target (keeps existing value when Referer is absent/same-page).
     */
    public static function captureReferer(Request $request, string $destinationKey): void
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return;
        }

        $url = self::sanitize($referer);

        if ($url === null) {
            return;
        }

        $destinationPath = '/'.ltrim($request->path(), '/');

        if (self::pathOnly($url) === $destinationPath) {
            return;
        }

        $request->session()->put(self::sessionKey($destinationKey), $url);
    }

    public static function get(string $destinationKey, ?string $fallback = null): ?string
    {
        $url = session(self::sessionKey($destinationKey));

        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        return self::sanitize($url) ?? $fallback;
    }

    public static function forget(string $destinationKey): void
    {
        session()->forget(self::sessionKey($destinationKey));
    }

    /**
     * Props helper for Inertia pages.
     *
     * @return array{return_to: string|null}
     */
    public static function prop(string $destinationKey, ?string $fallback = null): array
    {
        return [
            'return_to' => self::get($destinationKey, $fallback),
        ];
    }

    public static function sessionKey(string $destinationKey): string
    {
        return self::SESSION_PREFIX.$destinationKey;
    }

    /**
     * Accept only same-origin relative paths (path + query + fragment).
     */
    public static function sanitize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $url = trim($url);

        if (Str::startsWith($url, ['javascript:', 'data:', 'vbscript:'])) {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return Str::startsWith($url, '//') ? null : $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $appParts = parse_url($appUrl);

        if (($appParts['host'] ?? null) !== ($parts['host'] ?? null)) {
            return null;
        }

        $appPort = $appParts['port'] ?? null;
        $urlPort = $parts['port'] ?? null;

        if ($appPort !== $urlPort) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }

    private static function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}
