<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session trail for Back navigation.
 *
 * Opening a screen pushes it. Back pops it. A menu entry clears the trail and
 * starts again at that screen. The current page is the top of the stack; Back
 * returns to the page under it.
 */
final class NavigationStack
{
    public const HEADER = 'X-Navigation';

    public const RESET = 'reset';

    public const POP = 'pop';

    private const SESSION_KEY = 'navigation.stack';

    private const PREVIEW_KEY = 'navigation.stack.preview';

    private const DIRTY_KEY = 'navigation.stack.dirty';

    private const MAX_DEPTH = 30;

    /**
     * Project the stack for this request without writing the session.
     * The back target is the page under the current one.
     */
    public static function preview(Request $request): ?string
    {
        $dirty = self::shouldTrack($request);
        $stack = $dirty ? self::nextStack($request) : self::read($request);
        $request->attributes->set(self::PREVIEW_KEY, $stack);
        $request->attributes->set(self::DIRTY_KEY, $dirty);

        return self::backTarget($stack);
    }

    /**
     * Persist the preview after a real page response.
     * Redirects, downloads, and errors leave the trail unchanged.
     */
    public static function commit(Request $request, Response $response): void
    {
        if ($request->attributes->get(self::DIRTY_KEY) !== true) {
            return;
        }

        if (! $response->isSuccessful() || $response->isRedirection()) {
            return;
        }

        $disposition = $response->headers->get('Content-Disposition');

        if (is_string($disposition) && str_contains(strtolower($disposition), 'attachment')) {
            return;
        }

        $stack = $request->attributes->get(self::PREVIEW_KEY);

        if (! is_array($stack)) {
            return;
        }

        self::write($request, $stack);
    }

    /**
     * Preview and commit, for tests and direct calls.
     */
    public static function advance(Request $request): ?string
    {
        $back = self::preview($request);
        self::commit($request, new Response);

        return $back;
    }

    /**
     * @return list<string>
     */
    public static function all(Request $request): array
    {
        return self::read($request);
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

    private static function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->user() === null || $request->route() === null) {
            return false;
        }

        if (self::isPrefetch($request)) {
            return false;
        }

        $name = $request->route()?->getName();

        if (is_string($name) && (str_ends_with($name, '.export') || $name === 'export' || str_ends_with($name, 'generation-status'))) {
            return false;
        }

        if ($request->wantsJson() && ! $request->headers->has('X-Inertia')) {
            return false;
        }

        return true;
    }

    private static function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->headers->get('Purpose') ?: $request->headers->get('Sec-Purpose')));

        return str_contains($purpose, 'prefetch');
    }

    /**
     * @return list<string>
     */
    private static function nextStack(Request $request): array
    {
        $current = self::sanitize($request->fullUrl());
        $stack = self::read($request);

        if ($current === null) {
            return $stack;
        }

        $intent = $request->headers->get(self::HEADER);

        if ($intent === self::RESET) {
            return [$current];
        }

        $top = $stack === [] ? null : $stack[array_key_last($stack)];
        $parent = count($stack) >= 2 ? $stack[count($stack) - 2] : null;
        $returning = $intent === self::POP
            || ($parent !== null
                && $top !== null
                && self::pathOnly($parent) === self::pathOnly($current)
                && self::pathOnly($top) !== self::pathOnly($current));

        if ($returning) {
            return self::pop($request, $stack, $current);
        }

        if ($top === $current) {
            return $stack;
        }

        if ($top !== null && self::pathOnly($top) === self::pathOnly($current)) {
            $stack[array_key_last($stack)] = $current;

            return $stack;
        }

        $referer = self::sanitize($request->headers->get('referer'));

        if ($top !== null && $referer !== null && self::pathOnly($referer) === self::pathOnly($top)) {
            $stack[] = $current;

            return array_slice($stack, -self::MAX_DEPTH);
        }

        return [$current];
    }

    /**
     * @param  list<string>  $stack
     * @return list<string>
     */
    private static function pop(Request $request, array $stack, string $current): array
    {
        $referer = self::sanitize($request->headers->get('referer'));

        if ($stack !== []) {
            $top = $stack[array_key_last($stack)];
            $leavingTop = $referer === null || self::pathOnly($referer) === self::pathOnly($top);

            if ($leavingTop) {
                array_pop($stack);
            }
        }

        if ($stack === []) {
            return [$current];
        }

        $top = $stack[array_key_last($stack)];

        if (self::pathOnly($top) !== self::pathOnly($current)) {
            return [$current];
        }

        $stack[array_key_last($stack)] = $current;

        return $stack;
    }

    /**
     * @param  list<string>  $stack
     */
    private static function backTarget(array $stack): ?string
    {
        if (count($stack) < 2) {
            return null;
        }

        return $stack[count($stack) - 2];
    }

    /**
     * @return list<string>
     */
    private static function read(Request $request): array
    {
        $stack = self::store($request)->get(self::SESSION_KEY);

        if (! is_array($stack)) {
            return [];
        }

        $urls = [];

        foreach ($stack as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param  list<string>  $stack
     */
    private static function write(Request $request, array $stack): void
    {
        if ($stack === self::read($request)) {
            return;
        }

        self::store($request)->put(self::SESSION_KEY, array_values($stack));
    }

    private static function store(Request $request): Session
    {
        return $request->hasSession() ? $request->session() : session();
    }

    private static function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}
