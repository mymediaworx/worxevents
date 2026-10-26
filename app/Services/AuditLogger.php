<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function write(
        string $action,
        ?string $organizationId,
        ?Model $subject = null,
        array $properties = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject?->getKey(),
            'properties' => $this->redact($properties),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<mixed>  $properties
     * @return array<mixed>
     */
    private function redact(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (is_string($key) && preg_match('/password|secret|token|card|cvc|cvv|authorization/i', $key) === 1) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }
}
