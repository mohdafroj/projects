<?php

namespace App\Modules\Js\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class JsUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping JS demo user outside local/testing.');
            return;
        }

        $user = User::query()->updateOrCreate(
            ['employee_id' => 'JS-001'],
            [
                'name' => 'Dr. K. Pathak',
                'email' => 'js-001@vanisetu.local',
                'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                'section' => 'Systems and CB',
                'designation' => 'Joint Secretary',
                'language_competencies' => ['en', 'hi'],
                'is_active' => true,
            ],
        );

        $user->assignRole('js');
    }
}
