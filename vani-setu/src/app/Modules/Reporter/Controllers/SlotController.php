<?php

namespace App\Modules\Reporter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Reporter\Services\SlotReassignmentService;
use App\Modules\Reporter\Services\SlotRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotController extends Controller
{
    public function reassign(
        Request $request,
        SlotAssignment $assignment,
        SlotReassignmentService $service
    ): JsonResponse {
        abort_unless($request->user()?->isSupervisor() || $request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $newReporter = User::query()->findOrFail($validated['user_id']);
        $updated = $service->reassign($assignment, $newReporter, $request->user(), $validated['reason']);

        return response()->json(['assignment' => $updated]);
    }

    public function recovery(Request $request, Slot $slot, SlotRecoveryService $service): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['reporter', 'supervisor', 'admin']), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json($service->recordRecoveryState($slot, $validated['reason'] ?? 'network-drop'));
    }

    public function auditSweep(Request $request, Slot $slot, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->isSupervisor() || $request->user()?->isAdmin(), 403);

        $blocks = $slot->blocks()->get();
        $editedBlocks = $blocks->where('reporter_edit_count', '>', 0)->values();
        $editAuditCount = DB::table('audit_logs')
            ->where('action', 'capture.block.edit')
            ->where('payload->slot_code', $slot->code)
            ->count();
        $auditedBlockIds = DB::table('audit_logs')
            ->where('action', 'capture.block.edit')
            ->where('payload->slot_code', $slot->code)
            ->where('subject_type', (new Block())->getMorphClass())
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $missingAuditBlocks = $editedBlocks
            ->reject(fn ($block) => in_array((int) $block->id, $auditedBlockIds, true))
            ->values();

        $result = [
            'slot_id' => $slot->id,
            'block_count' => $blocks->count(),
            'edited_blocks' => $editedBlocks->count(),
            'edit_audit_count' => $editAuditCount,
            'missing_block_ids' => $missingAuditBlocks->pluck('id')->all(),
            'complete' => $missingAuditBlocks->isEmpty() && $editAuditCount >= $editedBlocks->count(),
        ];

        foreach ($missingAuditBlocks as $block) {
            $audit->log('reporter.slot.audit_sweep.recovered_edit', $block, [
                'slot_id' => $slot->id,
                'slot_code' => $slot->code,
                'block_id' => $block->id,
                'reporter_edit_count' => $block->reporter_edit_count,
                'version' => $block->version,
            ]);
        }

        $audit->log('reporter.slot.audit_sweep', $slot, $result);

        return response()->json($result);
    }

    public function unificationPreview(Request $request, Slot $slot, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['supervisor', 'chief', 'admin']), 403);

        $blocks = $slot->blocks()->get()->sortBy('start_ms')->values();
        $overlaps = [];
        $gaps = [];
        $previous = null;

        foreach ($blocks as $block) {
            if ($previous && $block->start_ms < $previous->end_ms) {
                $overlaps[] = [
                    'left_block_id' => $previous->id,
                    'right_block_id' => $block->id,
                    'overlap_ms' => $previous->end_ms - $block->start_ms,
                ];
            }

            if ($previous && $block->start_ms > $previous->end_ms) {
                $gaps[] = [
                    'after_block_id' => $previous->id,
                    'before_block_id' => $block->id,
                    'gap_ms' => $block->start_ms - $previous->end_ms,
                ];
            }

            $previous = $block;
        }

        $payload = [
            'slot_id' => $slot->id,
            'block_count' => $blocks->count(),
            'overlaps' => $overlaps,
            'gaps' => $gaps,
        ];

        $audit->log('reporter.slot.unification_previewed', $slot, $payload);

        return response()->json($payload);
    }

    public function finaliseDuration(Request $request, Slot $slot, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->isSupervisor() || $request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'duration_ms' => ['required', 'integer', 'min:60000', 'max:3600000'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $before = $slot->duration_ms;
        $slot->forceFill(['duration_ms' => $validated['duration_ms']])->save();

        $audit->log('reporter.slot.duration_finalised', $slot, [
            'slot_id' => $slot->id,
            'before_duration_ms' => $before,
            'after_duration_ms' => $slot->duration_ms,
            'reason' => $validated['reason'],
        ]);

        return response()->json(['slot' => $slot->fresh()]);
    }
}
