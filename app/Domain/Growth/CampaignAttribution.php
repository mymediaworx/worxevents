<?php

declare(strict_types=1);

namespace App\Domain\Growth;

final class CampaignAttribution
{
    /**
     * @return array{referral_code: ?string, utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    public function forOrder(): array
    {
        return [
            'referral_code' => $this->read('ref', 40),
            'utm_source' => $this->read('utm_source', 80),
            'utm_medium' => $this->read('utm_medium', 80),
            'utm_campaign' => $this->read('utm_campaign', 80),
        ];
    }

    private function read(string $key, int $max): ?string
    {
        $value = session('campaign.'.$key);

        if (! is_string($value) || $value === '' || strlen($value) > $max) {
            return null;
        }

        return $value;
    }
}
