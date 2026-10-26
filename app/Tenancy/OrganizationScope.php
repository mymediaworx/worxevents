<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Every tenant-owned query is limited to the organization resolved from the
 * hostname. A missing context matches nothing. A client-supplied organization
 * id is never the source of this value.
 */
final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(OrganizationManager::class);

        if ($manager->isBypassed()) {
            return;
        }

        $organizationId = $manager->id();

        if ($organizationId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
