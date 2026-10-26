<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\Event;
use App\Models\EventSession;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Builds timed-entry sessions from a daily or weekly recurrence.
 * Each new session keeps the duration of the first session and must sit
 * inside the event's start and end.
 */
final class SessionSeries
{
    public function generate(Event $event, string $frequency, DateTimeInterface $until): int
    {
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            throw new CheckoutRejected('Choose a daily or weekly recurrence.');
        }

        $first = $event->sessions()->orderBy('starts_at')->first();

        if (! $first instanceof EventSession) {
            throw new CheckoutRejected('Add the first session before repeating it.');
        }

        $seconds = $first->starts_at->diffInSeconds($first->ends_at, true);

        if ($seconds < 60) {
            throw new CheckoutRejected('The first session needs a duration.');
        }

        $cursor = $first->starts_at->copy()->add($frequency === 'weekly' ? '1 week' : '1 day');
        $endLimit = Carbon::instance(\DateTimeImmutable::createFromInterface($until));
        $created = 0;

        while ($cursor->lte($endLimit) && $created < 52) {
            $ends = $cursor->copy()->addSeconds((int) $seconds);

            if ($ends->gt($event->ends_at)) {
                break;
            }

            $exists = $event->sessions()->where('starts_at', $cursor)->exists();

            if (! $exists) {
                $event->sessions()->create([
                    'organization_id' => $event->organization_id,
                    'name' => $cursor->timezone($event->timezone)->format('D, M j g:i A'),
                    'entry_mode' => 'timed',
                    'starts_at' => $cursor,
                    'ends_at' => $ends,
                    'capacity' => $first->capacity,
                    'sort' => $event->sessions()->count(),
                ]);
                $created++;
            }

            $cursor = $cursor->add($frequency === 'weekly' ? '1 week' : '1 day');
        }

        $event->forceFill([
            'recurrence_frequency' => $frequency,
            'recurrence_until' => $endLimit,
        ])->save();

        return $created;
    }
}
