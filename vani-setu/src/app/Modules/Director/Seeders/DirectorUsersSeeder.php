<?php

namespace App\Modules\Director\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DirectorUsersSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);
        foreach (['publish.crc', 'publish.digital.sansad'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping director demo user outside local/testing.');
            return;
        }

        $user = User::query()->updateOrCreate(
            ['employee_id' => 'DIR-001'],
            [
                'name' => 'Dr. R. Nair',
                'email' => 'dir-001@vanisetu.local',
                'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                'section' => 'Publishing',
                'designation' => 'Director',
                'language_competencies' => ['en', 'hi'],
                'is_active' => true,
            ],
        );

        $user->assignRole('director');
    }
}
