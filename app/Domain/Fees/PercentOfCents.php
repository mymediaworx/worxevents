<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use InvalidArgumentException;

/**
 * Half-up percentage of an integer cent amount.
 *
 * numerator = amount_cents * basis_points
 * fee = intdiv(numerator, 10000)
 * if numerator % 10000 >= 5000 then fee += 1
 *
 * A remainder of exactly 5000 (half a cent) rounds away from zero.
 * 100 basis points is 1 percent. 300 basis points is 3 percent.
 */
final class PercentOfCents
{
    public static function apply(int $amountCents, int $basisPoints): int
    {
        if ($amountCents < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Amounts and rates cannot be negative.');
        }

        if ($basisPoints > 10_000) {
            throw new InvalidArgumentException('Basis points cannot exceed 10000.');
        }

        if ($amountCents > 0 && $basisPoints > 0 && $amountCents > intdiv(PHP_INT_MAX, $basisPoints)) {
            throw new InvalidArgumentException('Amount is too large to price in integer cents.');
        }

        $numerator = $amountCents * $basisPoints;
        $fee = intdiv($numerator, 10_000);

        if ($numerator % 10_000 >= 5_000) {
            $fee++;
        }

        return $fee;
    }
}
