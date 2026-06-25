<?php

namespace App\Modules\Synopsis\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SynopsisRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name' => 'synopsis_writer',
            'guard_name' => 'web',
        ]);

        $role->forceFill([
            'display_name' => 'Synopsis Writer',
        ])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
