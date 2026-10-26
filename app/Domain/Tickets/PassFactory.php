<?php

declare(strict_types=1);

namespace App\Domain\Tickets;

use App\Enums\IssuedTicketStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Ticket;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

/**
 * Issues an opaque token, stores only its SHA-256, and builds an SVG QR plus a PDF.
 * PNG QR codes are not produced here because the app does not require the GD extension.
 */
final class PassFactory
{
    public function issue(Attendee $attendee, Event $event, string $reference): TicketPass
    {
        $token = bin2hex(random_bytes(32));
        $options = new QROptions([
            'outputBase64' => false,
            'quietzoneSize' => 2,
        ]);

        $svg = (new QRCode($options))->render($token);

        if (! is_string($svg) || ! str_contains($svg, '<svg')) {
            throw new RuntimeException('QR rendering failed.');
        }

        /** @var list<list<bool>> $modules */
        $modules = (new QRCode($options))->addByteSegment($token)->getQRMatrix()->getMatrix(true);
        $when = $event->starts_at->timezone($event->timezone)->format('D, M j, Y g:i A T');

        Ticket::query()->create([
            'organization_id' => $attendee->organization_id,
            'attendee_id' => $attendee->id,
            'event_id' => $event->id,
            'order_id' => $attendee->order_id,
            'token_hash' => hash('sha256', $token),
            'status' => IssuedTicketStatus::Issued,
            'issued_at' => now(),
        ]);

        return new TicketPass(
            $token,
            $svg,
            TicketPdf::render($event->title, $attendee->name, $when, $reference, $token, $modules),
            'ticket-'.$attendee->id,
        );
    }
}
