<?php

namespace App\Modules\Chief\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChiefUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping chief demo users outside local/testing.');
            return;
        }

        foreach ($this->chiefs() as $chief) {
            $user = User::query()->updateOrCreate(
                ['employee_id' => $chief['employee_id']],
                [
                    'name' => $chief['name'],
                    'email' => strtolower($chief['employee_id']).'@vanisetu.local',
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'section' => 'Editorial',
                    'designation' => 'Chief',
                    'language_competencies' => [$chief['lang']],
                    'is_active' => true,
                ],
            );

            $user->assignRole('chief');
        }
    }

    private function chiefs(): array
    {
        return [
            ['employee_id' => 'CHF-EN-001', 'name' => 'Dr. R. Nair', 'lang' => 'en'],
            ['employee_id' => 'CHF-HI-001', 'name' => 'Smt. M. Sinha', 'lang' => 'hi'],
            ['employee_id' => 'CHF-EN-002', 'name' => 'Shri V. Ramakrishnan', 'lang' => 'en'],
            ['employee_id' => 'CHF-HI-002', 'name' => 'Dr. P. Sharma', 'lang' => 'hi'],
        ];
    }
}
