<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\MoneyFormat;
use App\Enums\EventStatus;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\TicketType;

final class EventSchema
{
    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, string $url, ?string $imageUrl): array
    {
        $event->loadMissing(['organization', 'venue', 'ticketTypes']);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => $event->seo_description ?: ($event->summary ?: $event->title),
            'startDate' => $event->starts_at->toAtomString(),
            'endDate' => $event->ends_at->toAtomString(),
            'eventStatus' => $event->status === EventStatus::Canceled
                ? 'https://schema.org/EventCancelled'
                : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $event->venue === null
                ? 'https://schema.org/OnlineEventAttendanceMode'
                : 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
            'organizer' => [
                '@type' => 'Organization',
                'name' => $event->organization?->name,
                'email' => $event->organization?->support_email,
            ],
        ];

        if ($event->venue !== null) {
            $data['location'] = [
                '@type' => 'Place',
                'name' => $event->venue->name,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $event->venue->address_line,
                    'addressLocality' => $event->venue->city,
                    'addressRegion' => $event->venue->region,
                    'postalCode' => $event->venue->postal_code,
                    'addressCountry' => $event->venue->country,
                ],
            ];
        } else {
            $data['location'] = [
                '@type' => 'VirtualLocation',
                'url' => $url,
            ];
        }

        if ($imageUrl !== null) {
            $data['image'] = [$imageUrl];
        }

        $prices = $event->ticketTypes
            ->filter(fn (TicketType $type): bool => $type->visibility === TicketVisibility::Public)
            ->map(fn (TicketType $type): int => $type->price_cents);

        if ($prices->isNotEmpty()) {
            $low = (int) $prices->min();
            $soldOut = $event->status === EventStatus::SoldOut || $event->ticketTypes->every(fn (TicketType $type): bool => $type->remaining() < $type->min_per_order);
            $data['offers'] = [
                '@type' => 'Offer',
                'price' => MoneyFormat::centsToDollars($low),
                'priceCurrency' => $event->organization?->currency ?: 'USD',
                'availability' => $soldOut ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                'url' => $url,
            ];
        }

        return $data;
    }
}
