<?php

namespace App\Http\Requests\Cms;

use App\Enums\LanguagePref;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CmsRules
{
    /**
     * @return list<mixed>
     */
    public static function lang(): array
    {
        return ['required', Rule::enum(LanguagePref::class)];
    }

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
     * @return list<mixed>
     */
    public static function slug(string $table, string $lang, ?int $ignoreId = null): array
    {
        return [
            'required',
            'string',
            'max:180',
            Rule::unique($table, 'slug')
                ->where('lang', $lang)
                ->whereNull('deleted_at')
                ->ignore($ignoreId),
        ];
    }
}
