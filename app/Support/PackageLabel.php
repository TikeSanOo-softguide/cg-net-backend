<?php

namespace App\Support;

use App\Models\Package;

final class PackageLabel
{
    public static function make(?Package $package, string $locale = 'en'): ?string
    {
        if ($package === null) {
            return null;
        }

        $currentLocale = app()->getLocale();
        app()->setLocale($locale);

        $networkKey = match ($locale) {
            'zh' => 'name_zh',
            'my' => 'name_my',
            default => 'name_en',
        };

        $network = $package->network?->{$networkKey} ?: $package->network?->name_en;

        $monthLabel = __('packages.months');

        $parts = array_values(
            array_filter([
                is_string($network) && $network !== '' ? $network : null,
                $package->speed?->mbps !== null ? $package->speed->mbps . ' Mbps' : null,
                $package->term?->months !== null ? $package->term->months . ' ' . $monthLabel : null,
            ]),
        );

        app()->setLocale($currentLocale);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
