<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Authorize.net signs the raw webhook body with HMAC-SHA512.
 * The header looks like: sha512=<hex>.
 */
final class AuthorizeNetSignature
{
    public function matches(string $rawBody, string $header, string $signatureKey): bool
    {
        $header = trim($header);
        $prefix = 'sha512=';

        if ($signatureKey === '' || strncasecmp($header, $prefix, strlen($prefix)) !== 0) {
            return false;
        }

        $given = substr($header, strlen($prefix));
        $expected = hash_hmac('sha512', $rawBody, $signatureKey);

        if (strlen($given) !== strlen($expected)) {
            return false;
        }

        return hash_equals(strtolower($expected), strtolower($given));
    }
}
