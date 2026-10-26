<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\PlatformMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformMember>
 */
class PlatformMemberFactory extends Factory
{
    protected $model = PlatformMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::query()->where('slug', 'platform_super_admin')->value('id'),
            'status' => MembershipStatus::Active,
        ];
    }
}
