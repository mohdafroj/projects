<?php

namespace App\Modules\Translator\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TranslatorUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping translator demo users outside local/testing.');
            return;
        }

        $users = [
            ['employee_id' => 'TRN-EN-001', 'name' => 'Dr. A. Saxena', 'language_competencies' => ['en_to_hi']],
            ['employee_id' => 'TRN-HI-001', 'name' => 'Smt. V. Bhattacharya', 'language_competencies' => ['hi_to_en']],
        ];

        foreach ($users as $definition) {
            $user = User::query()->updateOrCreate(
                ['employee_id' => $definition['employee_id']],
                [
                    'name' => $definition['name'],
                    'email' => strtolower($definition['employee_id']).'@vanisetu.local',
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'section' => 'E&T',
                    'designation' => 'Translator',
                    'language_competencies' => $definition['language_competencies'],
                    'is_active' => true,
                ],
            );

            $user->assignRole('translator');
        }
    }
}
