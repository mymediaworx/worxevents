<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\SeatBook;
use App\Tenancy\OrganizationManager;
use Illuminate\Console\Command;

final class ReleaseSeatHolds extends Command
{
    protected $signature = 'worxevents:seats:release';

    protected $description = 'Release expired reserved-seat holds';

    public function handle(SeatBook $seats, OrganizationManager $organizations): int
    {
        $released = $organizations->bypass(fn (): int => $seats->releaseExpired());
        $this->info('released='.$released);

        return self::SUCCESS;
    }
}
