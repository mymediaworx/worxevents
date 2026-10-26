<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if (array_key_exists('organization_id', $model->getAttributes())) {
                return;
            }

            $organizationId = app(OrganizationManager::class)->id();

            if ($organizationId !== null) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }
}
