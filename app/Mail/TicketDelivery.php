<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Tickets\TicketPass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TicketDelivery extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<TicketPass>  $passes
     */
    public function __construct(
        public readonly string $eventTitle,
        public readonly string $guestName,
        public readonly string $reference,
        public readonly string $orderUrl,
        public readonly array $passes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your tickets for '.$this->eventTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tickets');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $files = [];

        foreach ($this->passes as $pass) {
            $files[] = Attachment::fromData(fn (): string => $pass->pdf, $pass->filename.'.pdf')->withMime('application/pdf');
            $files[] = Attachment::fromData(fn (): string => $pass->svg, $pass->filename.'.svg')->withMime('image/svg+xml');
        }

        return $files;
    }
}
