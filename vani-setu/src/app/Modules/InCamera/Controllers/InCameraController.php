<?php

namespace App\Modules\InCamera\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\InCamera\Services\InCameraAccess;
use Illuminate\Http\Request;

class InCameraController extends Controller
{
    public function flag(Request $request, Block $block, AuditLogger $audit)
    {
        $validated = $request->validate([
            'committee_id' => ['nullable', 'integer', 'exists:committees,id'],
            'reason' => ['required', 'string', 'min:8'],
        ]);

        $block->forceFill([
            'committee_id' => $validated['committee_id'] ?? $block->getAttribute('committee_id'),
            'source_type' => 'committee',
            'in_camera_flag' => true,
        ])->save();

        $audit->log('in_camera.flag.applied', $block, [
            'committee_id' => $block->getAttribute('committee_id'),
            'reason' => $validated['reason'],
        ]);

        return response()->json(['block' => $block->fresh()]);
    }

    public function show(Request $request, Block $block, InCameraAccess $access, AuditLogger $audit)
    {
        $user = $request->user();

        abort_unless($access->canViewBlock($user, $block), 403);

        $audit->log('in_camera.block.viewed', $block, [
            'committee_id' => $block->getAttribute('committee_id'),
            'in_camera' => (bool) $block->getAttribute('in_camera_flag'),
        ]);

        return response()->json([
            'id' => $block->id,
            'text' => $access->redactedText($user, $block),
            'in_camera_flag' => (bool) $block->getAttribute('in_camera_flag'),
        ]);
    }
}
