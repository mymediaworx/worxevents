<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\RoleCatalog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        RoleCatalog::assertConsistent();

        foreach (RoleCatalog::permissions() as $slug => $name) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        foreach (RoleCatalog::roles() as $slug => $role) {
            $model = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $role['name'], 'scope' => $role['scope']],
            );

            $ids = Permission::query()->whereIn('slug', $role['permissions'])->pluck('id');
            $model->permissions()->sync($ids);
        }
    }
}
