<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\InventoryBook;
use App\Tenancy\OrganizationManager;
use Illuminate\Console\Command;

final class ReleaseReservations extends Command
{
    protected $signature = 'worxevents:reservations:release';

    protected $description = 'Release expired ticket holds back to inventory.';

    public function handle(InventoryBook $inventory, OrganizationManager $organizations): int
    {
        $released = $organizations->bypass(fn (): int => $inventory->releaseExpired());
        $this->line('released='.$released);

        return self::SUCCESS;
    }
}
