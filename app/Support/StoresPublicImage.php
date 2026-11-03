<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class StoresPublicImage
{
    public static function store(UploadedFile $file, string $directory, ?string $previousPath = null): string
    {
        $path = $file->store($directory, 'public');

        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store uploaded public image.');
        }

        if ($previousPath && $previousPath !== $path) {
            self::delete($previousPath);
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            if (!Storage::disk('public')->delete($path)) {
                throw new RuntimeException('Unable to delete public image.');
            }
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
