<?php

namespace App\Casts;

use App\Enums\LanguagePref;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<LanguagePref, string>
 */
class LanguagePrefCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): LanguagePref
    {
        return LanguagePref::tryFrom($this->normalize($value)) ?? LanguagePref::My;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof LanguagePref) {
            return $value->value;
        }

        return LanguagePref::tryFrom($this->normalize($value))?->value ?? LanguagePref::My->value;
    }

    private function normalize(mixed $value): string
    {
        return match ((string) $value) {
            'mm' => LanguagePref::My->value,
            'th' => LanguagePref::Zh->value,
            default => (string) $value,
        };
    }
}
