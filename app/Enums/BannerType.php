<?php

namespace App\Enums;

enum BannerType: string
{
    case WebBackground = 'no_internet';
    case WebPopUp = 'slow';
    case AppEntry = 'unstable';
    case AppPopUp = 'password';
}
