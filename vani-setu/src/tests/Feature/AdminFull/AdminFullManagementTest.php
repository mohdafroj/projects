<?php

namespace Tests\Feature\AdminFull;

use App\Modules\AdminFull\Models\AdminFullConfigToggle;
use App\Modules\AdminFull\Models\AdminFullCustomMemberMaster;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFullManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs(User::query()->where('employee_id', 'ADM-001')->firstOrFail());
    }

    public function test_admin_can_manage_user_and_role_assignments(): void
    {
        $this->postJson('/api/admin-full/roles', [
            'name' => 'review_admin',
            'display_name' => 'Review Admin',
            'permissions' => ['admin.users.manage'],
        ])
            ->assertOk()
            ->assertJsonPath('role.name', 'review_admin')
            ->assertJsonPath('role.permissions.0', 'admin.users.manage');

        $this->postJson('/api/admin-full/users', [
            'name' => 'Track A Admin',
            'employee_id' => 'ADM-T-A',
            'email' => 'adm-track-a@vanisetu.local',
            'password' => 'admin-full-secret',
            'section' => 'Systems',
            'designation' => 'Admin Officer',
            'language_competencies' => ['en', 'hi'],
            'roles' => ['review_admin'],
        ])
            ->assertOk()
            ->assertJsonPath('user.employee_id', 'ADM-T-A')
            ->assertJsonPath('user.roles.0', 'review_admin');

        $user = User::query()->where('employee_id', 'ADM-T-A')->firstOrFail();

        $this->patchJson("/api/admin-full/users/{$user->id}", [
            'is_active' => false,
            'roles' => ['admin'],
        ])
            ->assertOk()
            ->assertJsonPath('user.is_active', false)
            ->assertJsonPath('user.roles.0', 'admin');

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.role.create']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.user.create']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.user.update']);
    }

    public function test_admin_can_build_sitting_from_sitting_and_slot_templates(): void
    {
        $templateId = $this->postJson('/api/admin-full/sitting-templates', [
            'name' => 'Question Hour Template',
            'session_no' => 266,
            'default_status' => 'planned',
            'metadata' => ['house' => 'rajya_sabha'],
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('template.name', 'Question Hour Template')
            ->json('template.id');

        $this->postJson('/api/admin-full/slot-templates', [
            'sitting_template_id' => $templateId,
            'name' => 'Opening slot',
            'code_prefix' => 'QH',
            'start_offset_ms' => 0,
            'duration_ms' => 600000,
            'topic' => 'Question Hour',
            'lang_roles' => ['en', 'hi'],
            'is_active' => true,
        ])->assertOk();

        $this->postJson('/api/admin-full/sittings/from-template', [
            'template_id' => $templateId,
            'session_no' => 266,
            'sitting_no' => 909,
            'sitting_date' => '2026-05-20',
        ])
            ->assertOk()
            ->assertJsonPath('sitting.sitting_no', 909)
            ->assertJsonPath('sitting.slots.0.code', 'QH01');

        $sitting = Sitting::query()->where('session_no', 266)->where('sitting_no', 909)->firstOrFail();

        $this->assertSame(1, $sitting->slots()->count());
        $this->assertDatabaseHas('admin_full_sitting_templates', ['name' => 'Question Hour Template']);
        $this->assertDatabaseHas('admin_full_slot_templates', ['name' => 'Opening slot']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.sitting.instantiate_template']);
    }

    public function test_admin_can_manage_member_masters_config_and_audit_explorer(): void
    {
        $this->postJson('/api/admin-full/members', [
            'roster_id' => 'TA-M-001',
            'category' => 'member',
            'name_en' => 'SHRI TRACK A',
            'name_hi' => 'श्री ट्रैक ए',
            'party' => 'IND',
            'state_jur' => 'Delhi',
            'role_title' => 'Member',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('member.roster_id', 'TA-M-001');

        $this->postJson('/api/admin-full/custom-members', [
            'reference_code' => 'GUEST-001',
            'name_en' => 'Guest Speaker',
            'name_hi' => 'अतिथि वक्ता',
            'role_title' => 'Witness',
            'state_jur' => 'Committee',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('member.reference_code', 'GUEST-001');

        $this->patchJson('/api/admin-full/config', [
            'toggles' => [
                [
                    'key' => 'workflow.allow_slot_template_apply',
                    'enabled' => false,
                    'value' => ['reason_required' => true],
                    'description' => 'Temporarily freeze template application.',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonFragment(['key' => 'workflow.allow_slot_template_apply', 'enabled' => false]);

        $this->getJson('/api/admin-full/audit?action=admin_full&limit=20')
            ->assertOk()
            ->assertJsonPath('chain.valid', true)
            ->assertJsonFragment(['action' => 'admin_full.config.update']);

        $this->getJson('/api/admin-full/audit/verify')
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->assertSame(1, AdminFullCustomMemberMaster::query()->where('reference_code', 'GUEST-001')->count());
        $this->assertSame(false, AdminFullConfigToggle::query()->where('key', 'workflow.allow_slot_template_apply')->firstOrFail()->enabled);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.member.create']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_full.custom_member.create']);
    }

    public function test_non_admin_cannot_access_admin_full(): void
    {
        $reporter = User::query()->where('employee_id', 'RPT-001')->firstOrFail();
        Sanctum::actingAs($reporter);

        $this->getJson('/api/admin-full/summary')->assertForbidden();
    }
}
