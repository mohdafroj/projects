<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSupervisorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping demo supervisors outside local/testing.');
            return;
        }

        foreach ($this->supervisors() as $supervisor) {
            $user = User::query()->updateOrCreate(
                ['employee_id' => $supervisor['employee_id']],
                [
                    'name' => $supervisor['name'],
                    'email' => strtolower($supervisor['employee_id']).'@vanisetu.local',
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'section' => 'Reporting',
                    'designation' => 'Supervisor',
                    'language_competencies' => $supervisor['language_competencies'],
                    'is_active' => true,
                ],
            );

            $user->assignRole('supervisor');
        }
    }

    private function supervisors(): array
    {
        return [
            ['employee_id' => 'SUP-EN-001', 'name' => 'Dr. A. Menon', 'language_competencies' => ['en']],
            ['employee_id' => 'SUP-HI-001', 'name' => 'Smt. R. Tripathi', 'language_competencies' => ['hi']],
            ['employee_id' => 'SUP-EN-002', 'name' => 'Shri K. Subramaniam', 'language_competencies' => ['en']],
            ['employee_id' => 'SUP-HI-002', 'name' => 'Dr. N. Chaturvedi', 'language_competencies' => ['hi']],
        ];
    }
}
