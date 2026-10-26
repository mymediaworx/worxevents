<?php

declare(strict_types=1);

namespace App\Support;

final class Slugs
{
    /**
     * @var list<string>
     */
    private const RESERVED = [
        'www', 'app', 'admin', 'api', 'mail', 'static', 'assets', 'platform',
        'support', 'billing', 'status', 'cdn', 'ns1', 'ns2', 'localhost',
    ];

    public static function isWellFormed(string $slug): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $slug) === 1;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }
}
