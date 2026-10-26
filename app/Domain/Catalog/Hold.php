<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\InventoryReservation;

/**
 * The raw hold token exists only in this object. The database stores its hash.
 */
final class Hold
{
    public function __construct(
        public readonly InventoryReservation $reservation,
        public readonly string $token,
    ) {}
}
