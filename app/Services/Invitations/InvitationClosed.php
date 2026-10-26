<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use RuntimeException;

final class InvitationClosed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }
}
