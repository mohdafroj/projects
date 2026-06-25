<?php

use App\Modules\Asr\Services\HmacVerifier;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

Route::post('/asr/ingest', function (Request $request, HmacVerifier $hmac, AuditLogger $audit) {
    $secret = config('services.asr.ingest_secret');
    abort_unless(is_string($secret) && $secret !== '', 500);
    abort_unless($hmac->verify($request, $secret), 401);

    $validated = $request->validate([
        'slot_id' => ['required', 'integer', 'exists:slots,id'],
        'start_ms' => ['required', 'integer', 'min:0'],
        'end_ms' => ['nullable', 'integer', 'gte:start_ms'],
        'text' => ['required', 'string', 'max:12000'],
        'lang' => ['nullable', 'string', 'max:16'],
        'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        'provider_job_id' => ['nullable', 'string', 'max:255'],
    ]);

    $created = false;
    $block = DB::transaction(function () use ($validated, $audit, &$created) {
        /** @var Block $block */
        $block = Block::query()
            ->where('slot_id', $validated['slot_id'])
            ->where('start_ms', '<=', $validated['start_ms'])
            ->where('end_ms', '>=', $validated['start_ms'])
            ->lockForUpdate()
            ->first();

        if (! $block) {
            $slot = Slot::query()->lockForUpdate()->findOrFail($validated['slot_id']);
            $sequence = ((int) $slot->blocks()->max('sequence')) + 1;
            $endMs = $validated['end_ms'] ?? ($validated['start_ms'] + 30000);
            $lang = strtolower((string) ($validated['lang'] ?? 'en'));
            $lang = str_contains($lang, '-') ? strtok($lang, '-') : $lang;
            $lang = in_array($lang, ['en', 'hi', 'ta', 'ur', 'bn', 'mr'], true) ? $lang : 'en';
            $block = Block::query()->create([
                'slot_id' => $slot->id,
                'sequence' => $sequence,
                'start_ms' => $validated['start_ms'],
                'end_ms' => $endMs,
                'original_lang' => $lang,
                'chief_lang' => $lang === 'hi' ? 'hi' : 'en',
                'ai_action' => 'native',
                'ai_text' => $validated['text'],
                'text' => $validated['text'],
                'version' => 1,
                'reporter_edit_count' => 0,
            ]);
            $created = true;
        }

        $block->forceFill([
            'ai_text' => $validated['text'],
        ])->save();

        $audit->log('asr.block.ingested', $block, [
            'slot_id' => $block->slot_id,
            'start_ms' => $validated['start_ms'],
            'end_ms' => $validated['end_ms'] ?? null,
            'lang' => $validated['lang'] ?? $block->original_lang,
            'confidence' => $validated['confidence'] ?? null,
            'provider_job_id' => $validated['provider_job_id'] ?? null,
            'text_length' => mb_strlen($validated['text']),
        ]);

        return $block->fresh();
    });

    return response()->json([
        'block_id' => $block->id,
        'slot_id' => $block->slot_id,
        'created' => $created,
        'indexed' => true,
    ]);
});

Route::post('/auth/verify-realtime', function (Request $request) {
    $validated = $request->validate([
        'token' => ['required', 'string'],
        'document' => ['required', 'string', 'max:255'],
    ]);

    abort_unless(preg_match('/^(chief:\d+:(en|hi)|js:\d+)$/', $validated['document']) === 1, 422);

    $accessToken = PersonalAccessToken::findToken($validated['token']);
    $user = $accessToken?->tokenable;

    abort_unless($user && (! property_exists($user, 'is_active') || $user->is_active), 401);

    return response()->json([
        'user_id' => $user->id,
        'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values() : [],
        'permissions' => method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->values() : [],
    ]);
})->middleware('throttle:realtime-verify');

Route::post('/realtime/audit', function (Request $request, HmacVerifier $hmac, AuditLogger $audit) {
    $secret = config('services.asr.realtime_audit_secret');
    abort_unless(is_string($secret) && $secret !== '', 500);
    abort_unless($hmac->verify($request, $secret), 401);

    $validated = $request->validate([
        'action' => ['required', 'in:realtime.doc.join,realtime.doc.leave,realtime.doc.snapshot'],
        'document' => ['required', 'string', 'max:255'],
        'user_id' => ['nullable', 'integer'],
        'metadata' => ['nullable', 'array'],
    ]);

    $audit->log($validated['action'], null, [
        'document' => $validated['document'],
        'user_id' => $validated['user_id'] ?? null,
        'metadata' => $validated['metadata'] ?? [],
    ]);

    return response()->json(['ok' => true]);
});
