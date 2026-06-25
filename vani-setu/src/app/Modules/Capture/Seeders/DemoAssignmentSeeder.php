<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;

class DemoAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->assignments() as $slotCode => $lanes) {
            $slot = Slot::query()->where('code', $slotCode)->firstOrFail();

            foreach ($lanes as $lang => $employeeId) {
                $user = User::query()->where('employee_id', $employeeId)->firstOrFail();
                SlotAssignment::query()->updateOrCreate(
                    ['slot_id' => $slot->id, 'lang_role' => $lang],
                    [
                        'user_id' => $user->id,
                        'assignee_user_id' => null,
                        'status' => 'open',
                        'workflow_stage' => 'reporter',
                        'committed_at' => null,
                        'committed_audit_log_id' => null,
                        'last_workflow_action_at' => null,
                    ],
                );
            }
        }
    }

    private function assignments(): array
    {
        return [
            '1A' => ['en' => 'RPT-001', 'hi' => 'RPT-002', 'ta' => 'RPT-003', 'ur' => 'RPT-004'],
            '1B' => ['en' => 'RPT-006', 'hi' => 'RPT-007', 'ur' => 'RPT-008'],
            '1C' => ['en' => 'RPT-009', 'hi' => 'RPT-010', 'bn' => 'RPT-005'],
            '1D' => ['en' => 'RPT-011', 'hi' => 'RPT-012'],
            '1E' => ['en' => 'RPT-013', 'hi' => 'RPT-014', 'mr' => 'RPT-015'],
            '1F' => ['en' => 'RPT-001', 'hi' => 'RPT-002', 'ur' => 'RPT-008'],
            '2A' => ['en' => 'RPT-016', 'hi' => 'RPT-017'],
            '2B' => ['en' => 'RPT-018', 'hi' => 'RPT-014', 'ur' => 'RPT-004'],
            '2C' => ['en' => 'RPT-011', 'hi' => 'RPT-012', 'ur' => 'RPT-008'],
            '2D' => ['en' => 'RPT-013', 'hi' => 'RPT-014'],
            '2E' => ['en' => 'RPT-016', 'hi' => 'RPT-017'],
            '2F' => ['en' => 'RPT-018', 'hi' => 'RPT-002'],
        ];
    }
}
