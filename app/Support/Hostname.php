<?php

declare(strict_types=1);

namespace App\Support;

final class Hostname
{
    public static function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = rtrim($host, '.');

        if (str_contains($host, ':')) {
            $host = (string) preg_replace('/:\d+$/', '', $host);
        }

        return $host;
    }

    public static function isPlatform(string $host): bool
    {
        $domains = config('worxevents.platform_domains', []);

        return is_array($domains) && in_array(self::normalize($host), $domains, true);
    }
}
