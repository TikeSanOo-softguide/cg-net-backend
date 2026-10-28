<?php

namespace App\Http\Requests\Cms;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CmsRules
{
    /**
     * @return list<mixed>
     */
    public static function image(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            File::image()->max(5120),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function localized(string $prefix, bool $required, ?int $max = null): array
    {
        $rule = [$required ? 'required' : 'nullable', 'string'];

        if ($max !== null) {
            $rule[] = 'max:'.$max;
        }

        return [
            "{$prefix}_en" => $rule,
            "{$prefix}_zh" => $rule,
            "{$prefix}_my" => $rule,
        ];
    }

    public static function slugFrom(?string $source): string
    {
        $slug = Str::slug((string) $source);

        return $slug !== '' ? $slug : Str::lower(Str::random(8));
    }

    /**
     * @return list<mixed>
     */
    public static function slug(string $table, ?int $ignoreId = null): array
    {
        return [
            'required',
            'string',
            'max:180',
            Rule::unique($table, 'slug')
                ->whereNull('deleted_at')
                ->ignore($ignoreId),
        ];
    }
}
