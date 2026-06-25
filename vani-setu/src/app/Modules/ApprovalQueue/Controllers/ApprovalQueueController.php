<?php

namespace App\Modules\ApprovalQueue\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApprovalQueue\Models\ApprovalQueueAction;
use App\Modules\ApprovalQueue\Services\ApprovalQueueService;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalQueueController extends Controller
{
    public function __construct(private readonly ApprovalQueueService $queue)
    {
    }

    public function index(Request $request): array
    {
        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:48'],
            'priority' => ['nullable', 'in:high,normal,low'],
            'include_acknowledged' => ['nullable', 'boolean'],
            'include_snoozed' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $items = $this->queue->forUser($user, [
            ...$validated,
            'include_acknowledged' => $request->boolean('include_acknowledged'),
            'include_snoozed' => $request->boolean('include_snoozed'),
        ]);

        return [
            'items' => $items->values(),
            'summary' => $this->queue->summary($items),
        ];
    }

    public function summary(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $items = $this->queue->forUser($user);

        return ['summary' => $this->queue->summary($items)];
    }

    public function show(Request $request, string $itemKey): array|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $item = $this->queue->findForUser($user, $itemKey);

        if (! $item) {
            return response()->json(['message' => 'Queue item not found.'], 404);
        }

        return ['item' => $item];
    }

    public function acknowledge(Request $request, string $itemKey, AuditLogger $audit): array|JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->writeQueueAction($request, $itemKey, 'acknowledged', $validated['note'] ?? null, null, $audit);
    }

    public function snooze(Request $request, string $itemKey, AuditLogger $audit): array|JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'snoozed_until' => ['required', 'date', 'after:now'],
        ]);

        return $this->writeQueueAction($request, $itemKey, 'snoozed', $validated['note'] ?? null, $validated['snoozed_until'], $audit);
    }

    public function clear(Request $request, string $itemKey, AuditLogger $audit): array|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action = ApprovalQueueAction::query()
            ->where('user_id', $user->id)
            ->where('item_key', $itemKey)
            ->first();

        if (! $action) {
            return response()->json(['message' => 'Queue acknowledgement not found.'], 404);
        }

        $auditLog = $audit->log('approval_queue.clear', $action, [
            'item_key' => $itemKey,
            'previous_action' => $action->action,
            'module' => $action->module,
        ]);
        $action->delete();

        return ['cleared' => true, 'audit_log_id' => $auditLog->id];
    }

    private function writeQueueAction(
        Request $request,
        string $itemKey,
        string $actionName,
        ?string $note,
        ?string $snoozedUntil,
        AuditLogger $audit
    ): array|JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $item = $this->queue->findForUser($user, $itemKey);

        if (! $item) {
            return response()->json(['message' => 'Queue item not found.'], 404);
        }

        $action = ApprovalQueueAction::query()->updateOrCreate(
            ['user_id' => $user->id, 'item_key' => $itemKey],
            [
                'module' => $item['module'],
                'action' => $actionName,
                'note' => $note,
                'snoozed_until' => $snoozedUntil,
            ],
        );

        $auditLog = $audit->log("approval_queue.{$actionName}", $action, [
            'item_key' => $itemKey,
            'module' => $item['module'],
            'type' => $item['type'],
            'subject_id' => $item['subject_id'],
            'note' => $note,
            'snoozed_until' => $snoozedUntil,
        ]);

        $action->forceFill(['audit_log_id' => $auditLog->id])->save();

        return [
            'item' => $this->queue->findForUser($user, $itemKey),
            'queue_action' => $action->fresh(),
            'audit_log_id' => $auditLog->id,
        ];
    }
}
