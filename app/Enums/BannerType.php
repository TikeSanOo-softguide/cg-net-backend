<?php

namespace App\Enums;

enum BannerType: string
{
    case WebBackground = 'web_background';
    case WebPopUp = 'web_popup';
    case AppEntry = 'app_entry';
    case AppPopUp = 'app_popup';
}
