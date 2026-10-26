<?php

declare(strict_types=1);

namespace App\Domain\Growth;

use App\Models\EmailConsent;

final class EmailList
{
    public function grant(string $organizationId, string $email): void
    {
        $email = strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $existing = EmailConsent::query()->where('email', $email)->first();

        if ($existing instanceof EmailConsent) {
            $existing->forceFill([
                'consented_at' => now(),
                'unsubscribed_at' => null,
            ])->save();

            return;
        }

        EmailConsent::query()->create([
            'organization_id' => $organizationId,
            'email' => $email,
            'consented_at' => now(),
        ]);
    }

    /**
     * @return list<EmailConsent>
     */
    public function recipients(string $organizationId): array
    {
        return EmailConsent::query()
            ->where('organization_id', $organizationId)
            ->whereNull('unsubscribed_at')
            ->orderBy('email')
            ->limit(100)
            ->get()
            ->all();
    }
}
