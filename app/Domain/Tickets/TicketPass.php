<?php

declare(strict_types=1);

namespace App\Domain\Tickets;

final class TicketPass
{
    public function __construct(
        public readonly string $token,
        public readonly string $svg,
        public readonly string $pdf,
        public readonly string $filename,
    ) {}
}
