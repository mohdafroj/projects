<?php

namespace App\Modules\CommitteeSittings\Seeders;

use App\Modules\CommitteeSittings\Models\Committee;
use App\Modules\CommitteeSittings\Models\CommitteeParticipant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CommitteeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping committee demo data outside local/testing.');
            return;
        }

        $users = [
            ['employee_id' => 'COM-CHR-001', 'name' => 'Dr. Ananya Sen', 'email' => 'committee-chair@vanisetu.local', 'role' => 'committee_chair'],
            ['employee_id' => 'COM-SEC-001', 'name' => 'Shri Vivek Rao', 'email' => 'committee-secretary@vanisetu.local', 'role' => 'committee_secretary'],
            ['employee_id' => 'COM-STF-001', 'name' => 'Smt. Leela Menon', 'email' => 'committee-staff@vanisetu.local', 'role' => 'committee_secretariat'],
            ['employee_id' => 'COM-OBS-001', 'name' => 'Observer User', 'email' => 'committee-observer@vanisetu.local', 'role' => 'committee_observer'],
        ];

        foreach ($users as $definition) {
            $user = User::updateOrCreate(
                ['employee_id' => $definition['employee_id']],
                [
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'is_active' => true,
                    'language_competencies' => ['en', 'hi'],
                ],
            );
            $user->syncRoles([$definition['role']]);
        }

        $committee = Committee::query()->updateOrCreate(
            ['code' => 'DRPSC-IT'],
            [
                'name' => 'Department-related Parliamentary Standing Committee on Information Technology',
                'type' => 'DRPSC',
                'terms_of_reference' => 'Examine demands, evidence, and draft reports assigned to the committee.',
            ],
        );

        foreach ([
            ['employee_id' => 'COM-CHR-001', 'role' => 'committee_chair'],
            ['employee_id' => 'COM-SEC-001', 'role' => 'committee_secretary'],
            ['employee_id' => 'COM-STF-001', 'role' => 'committee_secretariat'],
            ['employee_id' => 'COM-OBS-001', 'role' => 'observer'],
        ] as $participant) {
            CommitteeParticipant::query()->updateOrCreate(
                [
                    'committee_id' => $committee->id,
                    'user_id' => User::query()->where('employee_id', $participant['employee_id'])->value('id'),
                    'role' => $participant['role'],
                ],
                []
            );
        }
    }
}
