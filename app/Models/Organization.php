<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'support_email',
        'slug',
        'status',
        'timezone',
        'currency',
        'brand_accent',
        'logo_path',
        'description',
        'setup_step',
        'setup_completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'setup_step' => 'integer',
            'setup_completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrganizationDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(OrganizationDomain::class);
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, [OrganizationStatus::Onboarding, OrganizationStatus::Active], true);
    }
}
