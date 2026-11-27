<?php

namespace App\Support;

class FcmText
{
    public static function truncateText(string $text, int $maxCharacters = 300): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxCharacters) {
            return $text;
        }

        return mb_substr($text, 0, $maxCharacters, 'UTF-8') . '...';
    }
}
