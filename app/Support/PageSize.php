<?php

declare(strict_types=1);

namespace App\Support;

final class PageSize
{
    public const MAX = 100;

    public static function clamp(int $requested, int $default = 25): int
    {
        $limit = (int) config('worxevents.pagination_max', self::MAX);

        if ($limit < 1) {
            $limit = self::MAX;
        }

        if ($requested < 1) {
            return min($default, $limit);
        }

        return min($requested, $limit);
    }
}
