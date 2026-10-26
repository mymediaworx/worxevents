<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use InvalidArgumentException;

/**
 * Integer-only money and percent conversion. No floats.
 */
final class MoneyFormat
{
    public static function dollarsToCents(string $amount): int
    {
        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Enter a dollar amount with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
    }

    public static function centsToDollars(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentToBps(string $percent): int
    {
        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percent)) {
            throw new InvalidArgumentException('Enter a percent with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $percent, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $bps = ((int) $whole) * 100 + (int) $fraction;

        if ($bps > 10_000) {
            throw new InvalidArgumentException('Percent cannot exceed 100.');
        }

        return $bps;
    }

    public static function bpsToPercent(int $bps): string
    {
        $whole = intdiv($bps, 100);
        $fraction = abs($bps % 100);

        if ($fraction === 0) {
            return (string) $whole;
        }

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
