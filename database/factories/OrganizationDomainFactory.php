<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationDomain>
 */
class OrganizationDomainFactory extends Factory
{
    protected $model = OrganizationDomain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'hostname' => Str::lower(Str::random(8)).'.test',
            'type' => 'subdomain',
            'verified_at' => now(),
            'is_primary' => true,
        ];
    }
}
