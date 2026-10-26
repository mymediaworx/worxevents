<?php

declare(strict_types=1);

namespace Tests\Feature\Readiness;

use App\Domain\Catalog\InventoryBook;
use App\Domain\Catalog\SeatBook;
use App\Domain\CheckIn\DoorCheckIn;
use App\Enums\EventStatus;
use App\Enums\IssuedTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketKind;
use App\Enums\TicketVisibility;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Seat;
use App\Models\SeatingPlan;
use App\Models\SeatingRow;
use App\Models\SeatingSection;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Tenancy\OrganizationManager;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 1) {
            DB::rollBack();
        }

        DB::disconnect('locktest');
        parent::tearDown();
    }

    public function test_an_inventory_lock_blocks_a_second_reservation(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $event = $this->event($workspace['organization']->id);
        $type = $this->ticket($workspace['organization']->id, $event->id);

        DB::beginTransaction();
        app(InventoryBook::class)->reserve($type, 1);
        $this->assertTrue($this->lockTimesOut('ticket_types', $type->id));
        DB::rollBack();

        $this->assertSame(0, (int) DB::table('ticket_types')->where('id', $type->id)->value('quantity_held'));
    }

    public function test_a_seat_lock_blocks_a_second_hold(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $event = $this->event($workspace['organization']->id);
        $seat = $this->seat($workspace['organization']->id, $event->id);

        DB::beginTransaction();
        app(SeatBook::class)->hold($seat);
        $this->assertTrue($this->lockTimesOut('seats', $seat->id));
        DB::rollBack();

        $this->assertSame('available', DB::table('seats')->where('id', $seat->id)->value('status'));
    }

    public function test_a_check_in_lock_blocks_a_second_scan(): void
    {
        $workspace = $this->workspace('alpha.test');
        app(OrganizationManager::class)->set($workspace['organization']);
        $event = $this->event($workspace['organization']->id);
        $token = bin2hex(random_bytes(32));
        $ticket = $this->issuedTicket($workspace['organization']->id, $event->id, $token);

        DB::beginTransaction();
        app(DoorCheckIn::class)->scan($workspace['user'], $event, $token);
        $this->assertTrue($this->lockTimesOut('tickets', $ticket->id));
        DB::rollBack();

        $this->assertNull(DB::table('tickets')->where('id', $ticket->id)->value('checked_in_at'));
        app(DoorCheckIn::class)->scan($workspace['user'], $event, $token);
        $this->assertNotNull(DB::table('tickets')->where('id', $ticket->id)->value('checked_in_at'));
    }

    private function lockTimesOut(string $table, string $id): bool
    {
        $connection = $this->secondConnection();
        $connection->statement('SET innodb_lock_wait_timeout = 1');

        try {
            $connection->transaction(function () use ($connection, $table, $id): void {
                $connection->table($table)->where('id', $id)->lockForUpdate()->first();
            });
        } catch (QueryException $exception) {
            return str_contains($exception->getMessage(), 'Lock wait timeout');
        }

        return false;
    }

    private function secondConnection(): Connection
    {
        config(['database.connections.locktest' => config('database.connections.mysql')]);
        DB::purge('locktest');

        return DB::connection('locktest');
    }

    private function event(string $organizationId): Event
    {
        return Event::query()->create([
            'organization_id' => $organizationId,
            'title' => 'Jazz Night',
            'slug' => 'jazz-night',
            'status' => EventStatus::Published,
            'timezone' => 'UTC',
            'starts_at' => '2026-11-02 00:00:00',
            'ends_at' => '2026-11-02 03:00:00',
            'published_at' => now(),
        ]);
    }

    private function ticket(string $organizationId, string $eventId): TicketType
    {
        return TicketType::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'name' => 'General',
            'kind' => TicketKind::Free,
            'price_cents' => 0,
            'currency' => 'USD',
            'quantity_total' => 5,
            'visibility' => TicketVisibility::Public,
            'min_per_order' => 1,
            'max_per_order' => 4,
        ]);
    }

    private function seat(string $organizationId, string $eventId): Seat
    {
        $plan = SeatingPlan::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'name' => 'Hall',
        ]);
        $section = SeatingSection::query()->create([
            'organization_id' => $organizationId,
            'seating_plan_id' => $plan->id,
            'name' => 'Orchestra',
            'price_zone' => 'A',
        ]);
        $row = SeatingRow::query()->create([
            'organization_id' => $organizationId,
            'seating_section_id' => $section->id,
            'label' => 'A',
        ]);

        return Seat::query()->create([
            'organization_id' => $organizationId,
            'seating_row_id' => $row->id,
            'label' => '1',
            'accessible' => true,
            'status' => 'available',
        ]);
    }

    private function issuedTicket(string $organizationId, string $eventId, string $token): Ticket
    {
        $order = Order::query()->create([
            'organization_id' => $organizationId,
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'status' => OrderStatus::Completed,
            'channel' => 'online',
            'currency' => 'USD',
            'ticket_subtotal_cents' => 0,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
            'reference' => 'LOCKTEST',
            'completed_at' => now(),
        ]);
        $type = $this->ticket($organizationId, $eventId);
        $line = OrderLine::query()->create([
            'organization_id' => $organizationId,
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'name_snapshot' => 'General',
            'kind_snapshot' => TicketKind::Free->value,
            'unit_price_cents' => 0,
            'quantity' => 1,
            'admits_snapshot' => 1,
            'line_total_cents' => 0,
        ]);
        $attendee = Attendee::query()->create([
            'organization_id' => $organizationId,
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'event_id' => $eventId,
            'ticket_type_id' => $type->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        return Ticket::query()->create([
            'organization_id' => $organizationId,
            'attendee_id' => $attendee->id,
            'event_id' => $eventId,
            'order_id' => $order->id,
            'token_hash' => hash('sha256', $token),
            'status' => IssuedTicketStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
