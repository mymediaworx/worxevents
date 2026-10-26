<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\CheckoutRejected;
use App\Domain\CheckIn\AttendeeExport;
use App\Domain\CheckIn\DoorCheckIn;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckInController extends Controller
{
    public function __construct(
        private readonly DoorCheckIn $door,
        private readonly AttendeeExport $export,
    ) {}

    public function index(Request $request): Response
    {
        return response()->view('organization.check-in', $this->page($request));
    }

    public function scan(Request $request): Response
    {
        $user = $this->user($request);
        $event = $this->event((string) $request->input('event_id'));

        try {
            $ticket = $this->door->scan($user, $event, (string) $request->input('token'));
            $ticket->load('attendee');
        } catch (CheckoutRejected $exception) {
            return $this->result($request, $exception->getMessage(), $exception->status);
        }

        return $this->result($request, 'Checked in '.$ticket->attendee?->name.'.', 200);
    }

    public function lookup(Request $request): Response
    {
        $event = $this->event((string) $request->input('event_id'));
        $matches = $this->door->lookup($event, (string) $request->input('query'));
        $data = $this->page($request);
        $data['matches'] = $matches;
        $data['eventId'] = $event->id;

        return response()->view('organization.check-in', $data);
    }

    public function admit(Request $request, Ticket $ticket): Response
    {
        $user = $this->user($request);
        $event = $this->event((string) $request->input('event_id'));

        try {
            $locked = $this->door->admitTicket($user, $event, $ticket);
            $locked->load('attendee');
        } catch (CheckoutRejected $exception) {
            return $this->result($request, $exception->getMessage(), $exception->status);
        }

        return $this->result($request, 'Checked in '.$locked->attendee?->name.'.', 200);
    }

    public function reverse(Request $request, Ticket $ticket): Response
    {
        $user = $this->user($request);

        try {
            $this->door->reverse($user, $ticket);
        } catch (CheckoutRejected $exception) {
            return $this->result($request, $exception->getMessage(), $exception->status);
        }

        return $this->result($request, 'Check-in reversed. The ticket can be scanned again.', 200);
    }

    public function export(Event $event): Response
    {
        return response($this->export->csv($event), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendees.csv"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Request $request): array
    {
        return [
            'title' => 'Check-in',
            'events' => Event::query()
                ->where('status', '!=', EventStatus::Draft->value)
                ->orderBy('starts_at')
                ->limit(100)
                ->get(),
            'recent' => CheckIn::query()->with(['event', 'ticket.attendee'])->latest()->limit(20)->get(),
            'eventId' => (string) $request->input('event_id'),
            'matches' => collect(),
        ];
    }

    private function result(Request $request, string $message, int $status): Response
    {
        $data = $this->page($request);
        $data['message'] = $message;
        $data['eventId'] = (string) $request->input('event_id', $data['eventId']);

        return response()->view('organization.check-in', $data, $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function event(string $id): Event
    {
        $event = Event::query()->whereKey($id)->first();

        if (! $event instanceof Event || $event->status === EventStatus::Draft) {
            abort(404);
        }

        return $event;
    }
}
