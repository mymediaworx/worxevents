<?php

declare(strict_types=1);

namespace App\Domain\Growth;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

final class PromoQr
{
    public function svg(string $url): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'quietzoneSize' => 2,
        ]);
        $svg = (new QRCode($options))->render($url);

        if (! is_string($svg) || ! str_contains($svg, '<svg')) {
            throw new RuntimeException('QR rendering failed.');
        }

        return $svg;
    }
}
