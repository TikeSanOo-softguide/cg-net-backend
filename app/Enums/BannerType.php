<?php

namespace App\Enums;

enum BannerType: string
{
    case WebBackground = 'web_background';
    case WebPopUp = 'web_popup';
    case AppEntry = 'app_entry';
    case AppPopUp = 'app_popup';

    public function limit(): ?int
    {
        return match ($this) {
            self::AppEntry => 1,
            self::AppPopUp => 2,
            default => null,
        };
    }
}
