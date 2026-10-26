<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeeRule;
use Illuminate\Database\Seeder;

class DefaultFeeRuleSeeder extends Seeder
{
    public function run(): void
    {
        FeeRule::query()->firstOrCreate(
            [
                'organization_id' => null,
                'effective_at' => '2026-01-01 00:00:00',
            ],
            [
                'percent_bps' => 300,
                'minimum_cents' => 100,
                'maximum_cents' => 500,
                'currency' => 'USD',
                'note' => 'Launch default. Applies to new orders only.',
            ],
        );
    }
}
