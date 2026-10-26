<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Models\AddOn;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderAddOn;
use Illuminate\Support\Facades\DB;

final class AddOnBook
{
    /**
     * @param  array<string, int>  $selections
     * @return array{cents: int, rows: list<array{add_on: AddOn, quantity: int, total: int}>}
     */
    public function take(Event $event, array $selections): array
    {
        return $this->collect($event, $selections, true);
    }

    /**
     * @param  array<string, int>  $selections
     * @return array{cents: int, rows: list<array{add_on: AddOn, quantity: int, total: int}>}
     */
    public function quote(Event $event, array $selections): array
    {
        return $this->collect($event, $selections, false);
    }

    /**
     * @param  array<string, int>  $selections
     * @return array{cents: int, rows: list<array{add_on: AddOn, quantity: int, total: int}>}
     */
    private function collect(Event $event, array $selections, bool $commit): array
    {
        $cents = 0;
        $rows = [];

        foreach ($selections as $id => $quantity) {
            if ($quantity < 1) {
                continue;
            }

            $query = AddOn::query()->whereKey($id)->where('event_id', $event->id);
            $addOn = $commit ? $query->lockForUpdate()->first() : $query->first();

            if (! $addOn instanceof AddOn) {
                throw new CheckoutRejected('That add-on is not part of this event.', 404);
            }

            $sold = $addOn->quantity_sold + $quantity;

            if ($sold > $addOn->quantity_total) {
                throw new CheckoutRejected('Not enough '.$addOn->name.' remain.', 409);
            }

            if ($commit) {
                $addOn->quantity_sold = $sold;
                $addOn->save();
            }

            $total = $addOn->price_cents * $quantity;
            $cents += $total;
            $rows[] = ['add_on' => $addOn, 'quantity' => $quantity, 'total' => $total];
        }

        return ['cents' => $cents, 'rows' => $rows];
    }

    /**
     * @param  list<array{add_on: AddOn, quantity: int, total: int}>  $rows
     */
    public function snapshot(Order $order, array $rows): void
    {
        foreach ($rows as $row) {
            OrderAddOn::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'add_on_id' => $row['add_on']->id,
                'name_snapshot' => $row['add_on']->name,
                'unit_price_cents' => $row['add_on']->price_cents,
                'quantity' => $row['quantity'],
                'line_total_cents' => $row['total'],
            ]);
        }
    }

    public function restore(Order $order): void
    {
        $rows = OrderAddOn::query()->where('order_id', $order->id)->get();

        foreach ($rows as $row) {
            DB::transaction(function () use ($row): void {
                if ($row->add_on_id !== null) {
                    $addOn = AddOn::query()->whereKey($row->add_on_id)->lockForUpdate()->first();

                    if ($addOn instanceof AddOn) {
                        $sold = $addOn->quantity_sold - $row->quantity;
                        $addOn->quantity_sold = $sold < 0 ? 0 : $sold;
                        $addOn->save();
                    }
                }

                $row->delete();
            });
        }
    }
}
