<?php

namespace App\Modules\Regional\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegionalUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping regional demo users outside local/testing.');
            return;
        }

        $users = [
            ['employee_id' => 'TRN-TA-001', 'name' => 'Smt. L. Krishnan', 'language_competencies' => ['ta_to_hi']],
            ['employee_id' => 'TRN-BN-001', 'name' => 'Shri S. Banerjee', 'language_competencies' => ['bn_to_hi']],
        ];

        foreach ($users as $definition) {
            $user = User::query()->updateOrCreate(
                ['employee_id' => $definition['employee_id']],
                [
                    'name' => $definition['name'],
                    'email' => strtolower($definition['employee_id']).'@vanisetu.local',
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'section' => 'E&T',
                    'designation' => 'Regional Language Specialist',
                    'language_competencies' => $definition['language_competencies'],
                    'is_active' => true,
                ],
            );

            $user->assignRole('translator');
        }
    }
}
