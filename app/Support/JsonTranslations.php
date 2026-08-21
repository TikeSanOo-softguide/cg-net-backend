<?php

namespace App\Support;

final class JsonTranslations
{
    /**
     * @var list<string>
     */
    public const LOCALES = ['en', 'my', 'zh'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function load(string $locale): array
    {
        if (! in_array($locale, self::LOCALES, true)) {
            return [];
        }

        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = lang_path("{$locale}.json");

        if (! is_readable($path)) {
            return self::$cache[$locale] = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return self::$cache[$locale] = is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, string>
     */
    public static function flatten(array $tree, string $prefix = ''): array
    {
        $flat = [];

        foreach ($tree as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = [...$flat, ...self::flatten($value, $full)];

                continue;
            }

            $flat[$full] = (string) $value;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    public static function dottedKeys(array $tree): array
    {
        $keys = array_keys(self::flatten($tree));
        sort($keys);

        return $keys;
    }
}
