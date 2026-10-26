<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Platform defaults have a null organization id. An organization may also have
 * its own rules. Other organizations' rules stay hidden.
 */
final class FeeRuleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(OrganizationManager::class);

        if ($manager->isBypassed()) {
            return;
        }

        $column = $model->qualifyColumn('organization_id');
        $organizationId = $manager->id();

        if ($organizationId === null) {
            $builder->whereNull($column);

            return;
        }

        $builder->where(function (Builder $query) use ($column, $organizationId): void {
            $query->where($column, $organizationId)->orWhereNull($column);
        });
    }
}
