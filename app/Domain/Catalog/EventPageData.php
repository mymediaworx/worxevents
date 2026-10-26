<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Payments\AuthorizeNetGateway;
use App\Enums\EventStatus;
use App\Enums\TicketVisibility;
use App\Models\Event;
use App\Models\OrganizationDomain;
use App\Models\PaymentSetting;
use App\Models\TicketType;
use App\Support\BrandColor;

final class EventPageData
{
    public function __construct(
        private readonly PublishedEvents $urls,
        private readonly EventSchema $schema,
        private readonly AuthorizeNetGateway $gateway,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Event $event, ?string $checkoutError = null): array
    {
        $event->loadMissing(['organization.domains', 'venue', 'category', 'tags', 'sessions', 'ticketTypes', 'questions']);
        $url = $this->urls->url($event);
        $image = $this->imageUrl($event);
        $registerable = $event->ticketTypes->filter(function (TicketType $type): bool {
            return $type->visibility !== TicketVisibility::Hidden;
        })->values();
        $hasPaidTickets = $registerable->contains(fn (TicketType $type): bool => $type->price_cents > 0);
        $setting = $this->gateway->isReady()
            ? PaymentSetting::query()->where('organization_id', $event->organization_id)->first()
            : null;

        return [
            'event' => $event,
            'title' => $event->seo_title ?: $event->title,
            'description' => $event->seo_description ?: ($event->summary ?: $event->title),
            'ogTitle' => $event->seo_title ?: $event->title,
            'ogDescription' => $event->seo_description ?: ($event->summary ?: $event->title),
            'ogType' => 'website',
            'ogUrl' => $url,
            'ogImage' => $image,
            'jsonLd' => $this->schema->build($event, $url, $image),
            'registerable' => $registerable,
            'canRegister' => $event->status === EventStatus::Published && $registerable->contains(
                fn (TicketType $type): bool => $type->remaining() >= $type->min_per_order,
            ),
            'hasPaidTickets' => $hasPaidTickets,
            'paymentsReady' => $setting instanceof PaymentSetting,
            'acceptApiLogin' => $setting instanceof PaymentSetting ? (string) $setting->api_login_id : null,
            'acceptClientKey' => $setting instanceof PaymentSetting ? (string) $setting->public_client_key : null,
            'acceptScript' => $setting instanceof PaymentSetting && $setting->environment === 'sandbox'
                ? 'https://jstest.authorize.net/v1/Accept.js'
                : null,
            'saleMessage' => $this->saleMessage($event, $hasPaidTickets),
            'checkoutError' => $checkoutError,
            'accent' => BrandColor::sanitize($event->organization?->brand_accent),
            'publicUrl' => $url,
        ];
    }

    private function imageUrl(Event $event): ?string
    {
        if ($event->image_path === null) {
            return null;
        }

        $event->loadMissing('organization.domains');
        $domain = $event->organization?->domains?->first(
            fn (OrganizationDomain $item): bool => $item->is_primary && $item->verified_at !== null,
        );
        $scheme = app()->environment('production') ? 'https' : request()->getScheme();

        if ($domain instanceof OrganizationDomain) {
            return $scheme.'://'.$domain->hostname.'/media/events/'.$event->id;
        }

        return url('/media/events/'.$event->id);
    }

    private function saleMessage(Event $event, bool $hasPaidTickets): string
    {
        return match ($event->status) {
            EventStatus::Paused => 'Registration is paused.',
            EventStatus::SoldOut => 'This event is sold out.',
            EventStatus::Canceled => 'This event was canceled.',
            EventStatus::Completed => 'This event has ended.',
            EventStatus::Draft => 'This event is not published.',
            EventStatus::Published => $hasPaidTickets
                ? 'Card payments use Authorize.net. The booking fee is calculated on the server.'
                : 'Registration is open for free tickets.',
        };
    }
}
