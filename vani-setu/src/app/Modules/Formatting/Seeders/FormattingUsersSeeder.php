<?php

namespace App\Modules\Formatting\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FormattingUsersSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'formatting.queue',
            'formatting.prepare',
            'formatting.validate',
            'formatting.crc',
            'formatting.dispatch',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'formatting', 'guard_name' => 'web']);
        $role->forceFill(['display_name' => 'Formatting Section In-charge'])->save();
        $role->syncPermissions($permissions);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping formatting demo user outside local/testing.');
            return;
        }

        $user = User::query()->updateOrCreate(
            ['employee_id' => 'FMT-001'],
            [
                'name' => 'Shri Yogesh Kumar',
                'email' => 'fmt-001@vanisetu.local',
                'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                'section' => 'E&T',
                'designation' => 'Section In-charge',
                'language_competencies' => ['en', 'hi'],
                'is_active' => true,
            ],
        );

        $user->assignRole('formatting');
    }
}
