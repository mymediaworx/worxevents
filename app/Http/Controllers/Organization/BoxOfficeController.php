<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\BoxOfficeSale;
use App\Domain\Catalog\CheckoutRejected;
use App\Domain\Fees\MoneyFormat;
use App\Domain\Tickets\TicketMailer;
use App\Enums\EventStatus;
use App\Enums\TicketVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BoxOfficeController extends Controller
{
    public function __construct(
        private readonly BoxOfficeSale $sales,
        private readonly TicketMailer $mailer,
    ) {}

    public function index(): Response
    {
        return response()->view('organization.box-office', $this->page());
    }

    public function store(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $event = Event::query()->whereKey((string) $request->input('event_id'))->first();
        $type = TicketType::query()->whereKey((string) $request->input('ticket_type_id'))->first();

        if (! $event instanceof Event || ! $type instanceof TicketType || $type->event_id !== $event->id) {
            return response()->view('organization.box-office', array_merge($this->page(), [
                'message' => 'Choose an event and a ticket.',
            ]), 422);
        }

        $name = trim((string) $request->input('attendee_name'));
        $email = trim((string) $request->input('attendee_email'));

        try {
            $result = $this->sales->sell(
                $event,
                $type,
                [['name' => $name, 'email' => $email]],
                $name,
                $email,
                (string) $request->input('tender'),
                $user,
            );
        } catch (CheckoutRejected $exception) {
            return response()->view('organization.box-office', array_merge($this->page(), [
                'message' => $exception->getMessage(),
            ]), $exception->status);
        }

        $this->mailer->send($result['order'], $event->title, $result['passes']);

        return response()->view('organization.box-office', array_merge($this->page(), [
            'status' => 'Sold '.$result['order']->reference.' · '.$result['order']->tender.' · $'.MoneyFormat::centsToDollars($result['order']->total_cents).'.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        $events = Event::query()
            ->where('status', EventStatus::Published->value)
            ->with(['ticketTypes' => function ($query): void {
                $query->where('visibility', '!=', TicketVisibility::Hidden->value);
            }])
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        return [
            'title' => 'Box office',
            'events' => $events,
        ];
    }
}
