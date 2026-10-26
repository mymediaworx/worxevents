<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoredImage
{
    public static function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'img';
        }

        $path = $file->storeAs($directory, strtolower((string) Str::ulid()).'.'.$extension, 'local');

        if (! is_string($path)) {
            throw new RuntimeException('Could not store the image.');
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && ! str_contains($path, '..')) {
            Storage::disk('local')->delete($path);
        }
    }
}
