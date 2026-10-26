<?php

declare(strict_types=1);

namespace App\Support;

final class CookieSecurity
{
    public static function secure(string $environment, mixed $configured): bool
    {
        if ($environment === 'production') {
            return true;
        }

        if ($configured === null || $configured === '') {
            return false;
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }
}
