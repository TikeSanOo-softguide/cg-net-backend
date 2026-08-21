<?php

namespace App\Console\Commands;

use App\Support\JsonTranslations;
use Illuminate\Console\Command;

class LangCheckCommand extends Command
{
    protected $signature = 'lang:check';

    protected $description = 'Fail if en/my/zh JSON translation files do not share the same key set';

    public function handle(): int
    {
        /** @var array<string, list<string>> $keysByLocale */
        $keysByLocale = [];

        foreach (JsonTranslations::LOCALES as $locale) {
            $path = lang_path("{$locale}.json");

            if (! is_readable($path)) {
                $this->error("Missing translation file: {$path}");

                return self::FAILURE;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (! is_array($decoded)) {
                $this->error("Invalid JSON: {$path}");

                return self::FAILURE;
            }

            $keysByLocale[$locale] = JsonTranslations::dottedKeys($decoded);
        }

        $union = array_values(array_unique(array_merge(...array_values($keysByLocale))));
        sort($union);

        $failed = false;

        foreach (JsonTranslations::LOCALES as $locale) {
            $missing = array_values(array_diff($union, $keysByLocale[$locale]));

            if ($missing !== []) {
                $failed = true;
                $this->error("[{$locale}] missing keys: ".implode(', ', $missing));
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $this->info('Translation files are in sync ('.count($union).' keys).');

        return self::SUCCESS;
    }
}
