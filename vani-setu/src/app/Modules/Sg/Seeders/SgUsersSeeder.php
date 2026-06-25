<?php

namespace App\Modules\Sg\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SgUsersSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'sg', 'guard_name' => 'web']);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping SG demo user outside local/testing.');
            return;
        }

        $user = User::query()->updateOrCreate(
            ['employee_id' => 'SG-001'],
            [
                'name' => 'Shri P.C. Mody',
                'email' => 'sg-001@vanisetu.local',
                'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                'section' => 'Table Office',
                'designation' => 'Secretary General',
                'language_competencies' => ['en', 'hi'],
                'is_active' => true,
            ],
        );

        $user->assignRole('sg');
    }
}
