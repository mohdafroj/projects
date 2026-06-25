<?php

namespace App\Modules\Core\Services\Audit;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogger
{
    private const LOCK_KEY = 42424242;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(string $action, ?Model $subject = null, array $payload = []): AuditLog
    {
        return DB::transaction(function () use ($action, $subject, $payload) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
            }

            $chainSegment = $this->chainSegment($action);
            $prevHash = AuditLog::query()
                ->toBase()
                ->where('chain_segment', $chainSegment)
                ->latest('id')
                ->value('this_hash');

            $actorId = $this->contextValue('audit.actor_id');
            $actorRole = $this->contextValue('audit.actor_role') ?? 'system';
            $requestIp = $this->contextValue('request.ip') ?: '0.0.0.0';
            $requestUa = $this->contextValue('request.ua') ?: 'system';
            $requestId = $this->contextValue('request.id') ?? (string) Str::uuid();
            $createdAt = now();
            $subjectType = $subject?->getMorphClass();
            $subjectId = $subject ? (string) $subject->getKey() : null;

            $preImage = AuditHash::preImage(
                $prevHash,
                $actorId,
                $actorRole,
                $action,
                $subjectType,
                $subjectId,
                $payload,
                $createdAt,
                $chainSegment,
            );

            $attributes = [
                'prev_hash' => $prevHash,
                'this_hash' => hash('sha256', $preImage),
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'chain_segment' => $chainSegment,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'payload' => $payload,
                'request_ip' => $requestIp,
                'request_ua' => Str::limit($requestUa, 255, ''),
                'request_id' => $requestId,
                'created_at' => $createdAt,
            ];

            DB::table('audit_logs')->insert([
                ...$attributes,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            return AuditLog::query()->where('this_hash', $attributes['this_hash'])->firstOrFail();
        });
    }

    private function contextValue(string $key): mixed
    {
        try {
            if (!app()->bound($key)) {
                return null;
            }
            $value = app($key);
            return $value instanceof \Closure ? $value() : $value;
        } catch (\Throwable) {
            return null;
        }
    }

    private function chainSegment(string $action): string
    {
        $override = $this->contextValue('audit.chain_segment');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return match (true) {
            str_starts_with($action, 'capture.') => 'speech_to_text',
            str_starts_with($action, 'reporter.') => 'reporter',
            str_starts_with($action, 'translator.') => 'translator',
            str_starts_with($action, 'committee.in_camera.'),
            str_starts_with($action, 'in_camera.') => 'committee.in_camera',
            str_starts_with($action, 'committee.') => 'committee',
            str_starts_with($action, 's2s.') => 'speech_to_speech',
            default => 'on_record',
        };
    }
}
