<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use RuntimeException;

final class GatewayUnconfigured extends RuntimeException {}
