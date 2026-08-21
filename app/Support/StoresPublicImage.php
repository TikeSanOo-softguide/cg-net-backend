<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoresPublicImage
{
    public static function store(UploadedFile $file, string $directory, ?string $previousPath = null): string
    {
        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $file->store($directory, 'public');
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
