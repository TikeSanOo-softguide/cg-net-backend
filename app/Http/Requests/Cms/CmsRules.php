<?php

namespace App\Http\Requests\Cms;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CmsRules
{
    /**
     * @return list<mixed>
     */
    public static function image(bool $required): array
    {
        return [$required ? 'required' : 'nullable', File::image()->max(5120)];
    }

    /**
     * @return list<mixed>
     */
    public static function slug(string $table, ?int $ignoreId = null, int $maxLength = 255): array
    {
        return [
            'required',
            'string',
            "max:{$maxLength}",
            Rule::unique($table, 'slug')->whereNull('deleted_at')->ignore($ignoreId),
        ];
    }
}
