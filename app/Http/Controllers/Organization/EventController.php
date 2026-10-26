<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\CheckoutRejected;
use App\Domain\Catalog\EventPageData;
use App\Domain\Catalog\FreeCheckout;
use App\Domain\Catalog\InventoryBook;
use App\Domain\Fees\MoneyFormat;
use App\Domain\Tickets\TicketMailer;
use App\Enums\EventStatus;
use App\Enums\QuestionField;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventTag;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Organization;
use App\Models\Venue;
use App\Services\AuditLogger;
use App\Support\CatalogSlug;
use App\Support\StoredImage;
use App\Tenancy\TenancyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class EventController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryBook $inventory,
        private readonly EventPageData $pages,
        private readonly FreeCheckout $checkout,
        private readonly TicketMailer $mailer,
    ) {}

    public function index(Request $request): Response
    {
        return response()->view('organization.events.index', [
            'title' => 'Events',
            'organization' => $this->organization($request),
            'events' => Event::query()->with(['venue', 'ticketTypes'])->orderByDesc('starts_at')->limit(100)->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('organization.events.form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $this->formData($request);
        $validated = $this->validateOr422($request, $this->rules(), 'organization.events.form', $data);

        try {
            $event = new Event(['organization_id' => $organization->id]);
            $this->fillEvent($event, $organization, $validated);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->route('events.create')->with('status', $exception->getMessage());
        }
        $event->status = EventStatus::Draft;
        $event->slug = $this->slug($organization->id, $event->title, null);
        $event->save();
        $this->syncTags($event, (string) ($validated['tags'] ?? ''));
        $this->audit->write('event.created', $organization->id, $event, ['title' => $event->title]);

        return redirect()->route('events.edit', $event)->with('status', 'Draft saved.');
    }

    public function edit(Request $request, Event $event): Response
    {
        $event->load(['venue', 'category', 'tags', 'sessions', 'ticketTypes', 'questions']);

        return response()->view('organization.events.form', $this->formData($request, $event));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $organization = $this->organization($request);
        $event->load(['tags', 'venue', 'category', 'sessions', 'ticketTypes', 'questions']);
        $validated = $this->validateOr422($request, array_merge($this->rules(), [
            'image' => ['nullable', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
        ]), 'organization.events.form', $this->formData($request, $event));

        try {
            $this->fillEvent($event, $organization, $validated);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->route('events.edit', $event)->with('status', $exception->getMessage());
        }

        if ($request->file('image') !== null) {
            StoredImage::delete($event->image_path);
            $event->image_path = StoredImage::store($request->file('image'), 'events/'.$organization->id);
        }

        $event->save();
        $this->syncTags($event, (string) ($validated['tags'] ?? ''));

        return redirect()->route('events.edit', $event)->with('status', 'Event saved.');
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        $event->loadCount('ticketTypes');

        if ($event->ticket_types_count < 1) {
            return redirect()->route('events.edit', $event)->with('status', 'Add a ticket type before publishing.');
        }

        if ($event->ends_at->lte($event->starts_at)) {
            return redirect()->route('events.edit', $event)->with('status', 'Set a start and end before publishing.');
        }

        $event->status = EventStatus::Published;
        $event->published_at = $event->published_at ?? now();
        $event->save();
        $fresh = $event->fresh();

        if ($fresh instanceof Event) {
            $this->inventory->refreshSaleStatus($fresh);
        }
        $this->audit->write('event.published', $event->organization_id, $event, [
            'status' => $event->status->value,
        ]);

        return redirect()->route('events.edit', $event)->with('status', 'Event published.');
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        $status = EventStatus::tryFrom((string) $request->input('status'));

        if ($status === null) {
            return redirect()->route('events.edit', $event)->with('status', 'Choose a status.');
        }

        if ($status === EventStatus::Published) {
            return $this->publish($request, $event);
        }

        $event->status = $status;
        $event->save();
        $this->audit->write('event.status', $event->organization_id, $event, [
            'status' => $status->value,
        ]);

        return redirect()->route('events.edit', $event)->with('status', 'Status updated.');
    }

    public function preview(Event $event): Response
    {
        $event->load(['organization.domains', 'venue', 'category', 'tags', 'sessions', 'ticketTypes', 'questions']);

        return response()->view('public.event', array_merge($this->pages->for($event), [
            'title' => 'Preview: '.$event->title,
            'robots' => 'noindex, nofollow',
            'preview' => true,
            'canRegister' => false,
        ]));
    }

    public function storeSession(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ], 'organization.events.form', $this->formData($request, $event->load(['tags', 'sessions', 'ticketTypes', 'questions', 'venue', 'category'])));

        $starts = $this->local((string) $validated['starts_at'], $event->timezone);
        $ends = $this->local((string) $validated['ends_at'], $event->timezone);

        if ($ends->lte($starts) || $starts->lt($event->starts_at) || $ends->gt($event->ends_at)) {
            return redirect()->route('events.edit', $event)->with('status', 'Session times must sit inside the event.');
        }

        $event->sessions()->create([
            'organization_id' => $event->organization_id,
            'name' => $validated['name'],
            'starts_at' => $starts,
            'ends_at' => $ends,
            'capacity' => $validated['capacity'] ?? null,
            'sort' => $event->sessions()->count(),
        ]);

        return redirect()->route('events.edit', $event)->with('status', 'Session added.');
    }

    public function storeTicket(Request $request, Event $event): RedirectResponse
    {
        $event->load('sessions');
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::enum(TicketKind::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'string'],
            'quantity_total' => ['required', 'integer', 'min:0', 'max:1000000'],
            'admits' => ['nullable', 'integer', 'min:1', 'max:20'],
            'min_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:20'],
            'sales_starts_at' => ['nullable', 'date'],
            'sales_ends_at' => ['nullable', 'date'],
            'visibility' => ['required', Rule::enum(TicketVisibility::class)],
            'session_id' => ['nullable', 'string'],
        ], 'organization.events.form', $this->formData($request, $event->load(['tags', 'sessions', 'ticketTypes', 'questions', 'venue', 'category'])));

        $kind = TicketKind::from((string) $validated['kind']);

        try {
            $price = $this->price($kind, (string) ($validated['price'] ?? ''));
        } catch (CheckoutRejected $exception) {
            return redirect()->route('events.edit', $event)->with('status', $exception->getMessage());
        }
        $min = (int) $validated['min_per_order'];
        $max = (int) $validated['max_per_order'];

        if ($max < $min) {
            return redirect()->route('events.edit', $event)->with('status', 'The maximum per order cannot be below the minimum.');
        }

        $admits = (int) ($validated['admits'] ?? 1);

        if ($kind === TicketKind::Group && $admits < 2) {
            return redirect()->route('events.edit', $event)->with('status', 'A group ticket must admit at least two people.');
        }

        if ($kind !== TicketKind::Group) {
            $admits = 1;
        }

        $sessionId = $validated['session_id'] ?? null;

        if (is_string($sessionId) && $sessionId !== '' && ! $event->sessions->contains('id', $sessionId)) {
            return redirect()->route('events.edit', $event)->with('status', 'Choose a session on this event.');
        }

        $event->ticketTypes()->create([
            'organization_id' => $event->organization_id,
            'session_id' => is_string($sessionId) && $sessionId !== '' ? $sessionId : null,
            'name' => $validated['name'],
            'kind' => $kind,
            'description' => $validated['description'] ?? null,
            'price_cents' => $price,
            'currency' => 'USD',
            'quantity_total' => (int) $validated['quantity_total'],
            'admits' => $admits,
            'min_per_order' => $min,
            'max_per_order' => $max,
            'sales_starts_at' => $this->optionalLocal($validated['sales_starts_at'] ?? null, $event->timezone),
            'sales_ends_at' => $this->optionalLocal($validated['sales_ends_at'] ?? null, $event->timezone),
            'visibility' => TicketVisibility::from((string) $validated['visibility']),
        ]);

        return redirect()->route('events.edit', $event)->with('status', 'Ticket type saved.');
    }

    public function storeQuestion(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validateOr422($request, [
            'label' => ['required', 'string', 'max:200'],
            'field_type' => ['required', Rule::enum(QuestionField::class)],
            'required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:2000'],
        ], 'organization.events.form', $this->formData($request, $event->load(['tags', 'sessions', 'ticketTypes', 'questions', 'venue', 'category'])));

        $type = QuestionField::from((string) $validated['field_type']);
        $options = null;

        if ($type === QuestionField::Select) {
            $options = array_values(array_filter(array_map(
                trim(...),
                preg_split("/\r\n|\n|\r/", (string) ($validated['options'] ?? '')) ?: [],
            ), fn (string $option): bool => $option !== ''));

            if ($options === []) {
                return redirect()->route('events.edit', $event)->with('status', 'A select question needs at least one option.');
            }
        }

        $event->questions()->create([
            'organization_id' => $event->organization_id,
            'label' => $validated['label'],
            'field_type' => $type,
            'required' => $request->boolean('required'),
            'options' => $options,
            'sort' => $event->questions()->count(),
        ]);

        return redirect()->route('events.edit', $event)->with('status', 'Question saved.');
    }

    public function reissue(Order $order): RedirectResponse
    {
        try {
            $passes = $this->checkout->reissue($order);
        } catch (CheckoutRejected $exception) {
            return redirect()->route('reports')->with('status', $exception->getMessage());
        }

        $order->load('lines');
        $line = $order->lines->first();
        $title = $line instanceof OrderLine ? $line->name_snapshot : 'your event';
        $this->mailer->send($order, $title, $passes);

        return redirect()->route('reports')->with('status', 'Replacement tickets were emailed. Previous QR codes no longer scan.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillEvent(Event $event, Organization $organization, array $validated): void
    {
        $timezone = (string) $validated['timezone'];
        $starts = $this->local((string) $validated['starts_at'], $timezone);
        $ends = $this->local((string) $validated['ends_at'], $timezone);

        if ($ends->lte($starts)) {
            throw new \InvalidArgumentException('End must be after the start.');
        }

        $venueId = $validated['venue_id'] ?? null;

        if (is_string($venueId) && $venueId !== '' && ! Venue::query()->whereKey($venueId)->exists()) {
            $venueId = null;
        }

        $event->fill([
            'organization_id' => $organization->id,
            'venue_id' => is_string($venueId) && $venueId !== '' ? $venueId : null,
            'category_id' => $this->categoryId($organization->id, (string) ($validated['category_name'] ?? '')),
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'description' => $validated['description'] ?? null,
            'timezone' => $timezone,
            'is_multi_day' => filter_var($validated['is_multi_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'sales_starts_at' => $this->optionalLocal($validated['sales_starts_at'] ?? null, $timezone),
            'sales_ends_at' => $this->optionalLocal($validated['sales_ends_at'] ?? null, $timezone),
            'capacity' => $validated['capacity'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'timezone' => ['required', 'timezone'],
            'is_multi_day' => ['nullable'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'sales_starts_at' => ['nullable', 'date'],
            'sales_ends_at' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'venue_id' => ['nullable', 'string'],
            'category_name' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'string', 'max:300'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Event $event = null): array
    {
        return [
            'title' => $event === null ? 'New event' : 'Edit event',
            'organization' => $this->organization($request),
            'event' => $event,
            'venues' => Venue::query()->orderBy('name')->get(),
            'kinds' => TicketKind::cases(),
            'visibilities' => TicketVisibility::cases(),
            'questionFields' => QuestionField::cases(),
            'statuses' => EventStatus::cases(),
        ];
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');

        if (! $organization instanceof Organization) {
            throw TenancyException::notFound();
        }

        return $organization;
    }

    private function slug(string $organizationId, string $title, ?string $ignoreId): string
    {
        return CatalogSlug::from($title, function (string $slug) use ($organizationId, $ignoreId): bool {
            return Event::query()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();
        });
    }

    private function categoryId(string $organizationId, string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);
        $existing = EventCategory::query()->where('slug', $slug)->first();

        if ($existing instanceof EventCategory) {
            return $existing->id;
        }

        $category = EventCategory::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => CatalogSlug::from($name, fn (string $candidate): bool => EventCategory::query()->where('slug', $candidate)->exists()),
        ]);

        return $category->id;
    }

    private function syncTags(Event $event, string $tags): void
    {
        $names = array_values(array_filter(array_map(trim(...), explode(',', $tags)), fn (string $name): bool => $name !== ''));
        $ids = [];

        foreach ($names as $name) {
            $slug = Str::slug($name);

            if ($slug === '') {
                $slug = CatalogSlug::from($name, fn (): bool => false);
            }

            $tag = EventTag::query()->firstOrCreate(
                ['organization_id' => $event->organization_id, 'slug' => $slug],
                ['name' => $name],
            );
            $ids[] = $tag->id;
        }

        $event->tags()->sync($ids);
    }

    private function price(TicketKind $kind, string $amount): int
    {
        $amount = trim($amount);

        if ($kind === TicketKind::Free) {
            if ($amount !== '' && $amount !== '0' && $amount !== '0.00') {
                throw new CheckoutRejected('Free tickets must cost $0.00.');
            }

            return 0;
        }

        if ($amount === '') {
            return 0;
        }

        try {
            $cents = MoneyFormat::dollarsToCents($amount);
        } catch (\InvalidArgumentException) {
            throw new CheckoutRejected('Enter a price in dollars and cents.');
        }

        if ($kind === TicketKind::Paid && $cents < 1) {
            throw new CheckoutRejected('A paid ticket needs a price above zero.');
        }

        return $cents;
    }

    private function local(string $value, string $timezone): Carbon
    {
        return Carbon::parse($value, $timezone)->utc();
    }

    private function optionalLocal(mixed $value, string $timezone): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->local($value, $timezone);
    }
}
