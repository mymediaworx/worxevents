<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class CatalogSlug
{
    public static function from(string $name, callable $exists): string
    {
        $base = Str::slug($name);
        $base = trim($base, '-');

        if ($base === '' || strlen($base) < 3) {
            $base = 'item-'.Str::lower(Str::random(6));
        }

        $base = substr($base, 0, 70);
        $slug = $base;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = substr($base, 0, 60).'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
