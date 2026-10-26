<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use LogicException;

/**
 * A gateway call may accept a refund for processing. It must not report the
 * refund as complete. OrganizerRefundWorkflow::confirm() is the only writer
 * of the completed status, and only after a later provider confirmation.
 */
final class ProviderResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly bool $completed,
        public readonly ?string $reference,
        public readonly string $detail,
        public readonly string $status = '',
        public readonly ?string $accountLastFour = null,
        public readonly ?string $invoiceNumber = null,
    ) {
        if ($this->completed) {
            throw new LogicException('Gateway results stay incomplete until a separate confirmation.');
        }
    }
}
