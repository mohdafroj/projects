<?php

namespace Tests\Feature\Regional;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Regional\Models\RegionalCase;
use App\Modules\Regional\Seeders\RegionalUsersSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\ModuleTestCase;

class RegionalWorkflowTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedModuleBase();
        $this->seed(RegionalUsersSeeder::class);
    }

    public function test_intake_detects_regional_language_and_routes_to_specialist(): void
    {
        $specialist = $this->specialist('TRN-TA-001');
        Sanctum::actingAs($specialist);

        $this->postJson('/api/regional/cases', [
            'source_text' => 'தமிழில் உரை உள்ளது',
            'domain' => 'parliamentary',
        ])->assertCreated()
            ->assertJsonPath('case.source_language', 'ta')
            ->assertJsonPath('case.specialist_user_id', $specialist->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'regional.case.routed']);
    }

    public function test_translation_uses_external_adapter_when_available_and_cross_check_commits_to_block(): void
    {
        Http::fake([
            '*/v1/translate' => Http::response([
                'translation' => 'माननीय सदस्य ने क्षेत्रीय भाषा में कहा।',
                'provider' => 'indictrans2',
                'model_version' => 'test-model',
                'confidence' => 0.91,
            ]),
        ]);

        $specialist = $this->specialist('TRN-TA-001');
        $block = Block::query()->firstOrFail();
        Sanctum::actingAs($specialist);

        $caseId = $this->postJson('/api/regional/cases', [
            'block_id' => $block->id,
            'source_text' => 'தமிழில் உரை உள்ளது',
        ])->assertCreated()->json('case.id');

        $this->postJson("/api/regional/cases/{$caseId}/translate", [
            'specialist_translation' => 'विशेषज्ञ अनुवाद',
        ])->assertOk()
            ->assertJsonPath('case.status', 'translated')
            ->assertJsonPath('case.translation_meta.model_version', 'test-model');

        $this->postJson("/api/regional/cases/{$caseId}/cross-check", [
            'result' => 'passed',
            'score' => 96,
            'notes' => 'Terminology verified.',
        ])->assertOk()
            ->assertJsonPath('case.status', 'cross_checked');

        $this->postJson("/api/regional/cases/{$caseId}/commit")
            ->assertOk()
            ->assertJsonPath('case.status', 'committed');

        $this->assertSame('विशेषज्ञ अनुवाद', $block->fresh()->translated_text);
        $this->assertDatabaseHas('audit_logs', ['action' => 'regional.block.routed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'regional.case.translated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'regional.case.cross_checked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'regional.case.committed']);
    }

    public function test_translation_falls_back_to_deterministic_dev_stub(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $case = RegionalCase::query()->create([
            'specialist_user_id' => $this->specialist('TRN-TA-001')->id,
            'source_language' => 'ta',
            'target_language' => 'hi',
            'detector' => 'unicode-script',
            'detection_confidence' => 0.9,
            'source_text' => 'தமிழில் உரை உள்ளது',
            'status' => 'routed',
        ]);
        Sanctum::actingAs($case->specialist);

        $this->postJson("/api/regional/cases/{$case->id}/translate")
            ->assertOk()
            ->assertJsonPath('case.translation_meta.provider', 'deterministic-dev-stub');
    }

    public function test_queue_is_scoped_to_specialist_language_competency(): void
    {
        $ta = $this->specialist('TRN-TA-001');
        $bn = $this->specialist('TRN-BN-001');
        RegionalCase::query()->create([
            'specialist_user_id' => $ta->id,
            'source_language' => 'ta',
            'target_language' => 'hi',
            'detector' => 'unicode-script',
            'detection_confidence' => 0.9,
            'source_text' => 'தமிழில் உரை உள்ளது',
            'status' => 'routed',
        ]);
        RegionalCase::query()->create([
            'specialist_user_id' => $bn->id,
            'source_language' => 'bn',
            'target_language' => 'hi',
            'detector' => 'unicode-script',
            'detection_confidence' => 0.9,
            'source_text' => 'বাংলা বক্তব্য',
            'status' => 'routed',
        ]);

        Sanctum::actingAs($ta);

        $this->getJson('/api/regional/queue')
            ->assertOk()
            ->assertJsonFragment(['source_language' => 'ta'])
            ->assertJsonMissing(['source_language' => 'bn']);
    }

    private function specialist(string $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->firstOrFail();
    }
}
