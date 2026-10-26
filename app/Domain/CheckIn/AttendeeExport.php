<?php

declare(strict_types=1);

namespace App\Domain\CheckIn;

use App\Enums\IssuedTicketStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Ticket;

final class AttendeeExport
{
    public function csv(Event $event): string
    {
        $attendees = Attendee::query()
            ->where('event_id', $event->id)
            ->with(['order', 'tickets'])
            ->orderBy('name')
            ->limit(100)
            ->get();

        $lines = ['name,email,order_reference,order_status,ticket_status,checked_in_at'];

        foreach ($attendees as $attendee) {
            $ticket = $attendee->tickets->first(
                fn (Ticket $row): bool => $row->status === IssuedTicketStatus::Issued,
            ) ?? $attendee->tickets->first();
            $checkedIn = $ticket instanceof Ticket && $ticket->checked_in_at !== null
                ? $ticket->checked_in_at->utc()->format('Y-m-d H:i:s')
                : '';
            $lines[] = implode(',', [
                $this->cell($attendee->name),
                $this->cell($attendee->email),
                $this->cell($attendee->order->reference),
                $this->cell($attendee->order->status->value),
                $this->cell($ticket instanceof Ticket ? $ticket->status->value : ''),
                $this->cell($checkedIn),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function cell(string $value): string
    {
        if (preg_match('/^[=+\-@]/', $value) === 1 || str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
