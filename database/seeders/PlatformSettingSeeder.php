<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'pagination_max' => ['limit' => 100],
            'queue_max_seconds' => ['seconds' => 20],
            'booking_fee_label' => ['label' => 'Booking fee'],
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
