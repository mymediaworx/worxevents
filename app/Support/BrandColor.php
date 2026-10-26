<?php

declare(strict_types=1);

namespace App\Support;

final class BrandColor
{
    public static function sanitize(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        $color = trim($color);

        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return null;
        }

        return strtolower($color);
    }
}
