<?php

namespace App\Modules\SpeechToSpeech\Commands;

use App\Modules\SpeechToSpeech\Models\S2sOutput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Iter 17 backfill — Iter 15b identified ~19 s2s_outputs rows (mostly
 * hi-IN) that carry status='translation_degraded' even though the
 * upstream STT produced no source_text. Those are actually STT
 * outages: translation was never attempted, so the UI degraded badge
 * is misleading. Iter 16b fixed the FUTURE pipeline (empty source now
 * yields provider_pending + provider_used=stt_unavailable inside
 * output_meta). This command cleans up the EXISTING bad rows.
 *
 * Iter 19 extension — adds two more residual cleanup modes:
 *   * deterministic-draft-leak: rows whose text_output came from the
 *     IndicTrans2 deterministic draft path even though STT never
 *     produced source_text (provider_response.provider_used =
 *     sarvam_stt_unavailable) OR whose text_output literally contains
 *     the sticky '[IndicTrans2 deterministic draft' label.
 *   * null-stale-audio: rows already backfilled by THIS command in a
 *     prior run still carry an audio_output_path even though TTS was
 *     never called. UI may try to play empty audio — null the path.
 *
 * Default mode is 'all' which runs the three sub-passes in order:
 * empty-source -> deterministic-draft-leak -> null-stale-audio.
 *
 * Criteria for the original empty-source pass (ALL must hold):
 *   - s2s_outputs.status = 'translation_degraded'
 *   - linked s2s_segments.source_text is NULL or empty
 *   - s2s_outputs.text_output is NULL or empty
 *   - row is older than 1 hour (don't touch in-flight rows)
 *
 * Rewrite to status='provider_pending' and stamp
 * output_meta.provider_used='stt_unavailable' (s2s_outputs has no
 * top-level provider_used column — provenance lives in the JSON blob,
 * matching what SarvamSpeechPipeline writes via output_meta.provider_payload).
 */
class BackfillDegradedStatusCommand extends Command
{
    private const MODE_EMPTY_SOURCE = 'empty-source';
    private const MODE_DRAFT_LEAK = 'deterministic-draft-leak';
    private const MODE_NULL_AUDIO = 'null-stale-audio';
    private const MODE_SCRUB_AUDIO_META = 'scrub-audio-base64-meta';
    private const MODE_PASSTHROUGH_SHORT = 'passthrough-short';
    private const MODE_SCRIPT_VALIDATED = 'script-validated';
    private const MODE_ALL = 'all';

    protected $signature = 's2s:backfill-degraded-status
        {--mode=all : Sub-pass to run (empty-source|deterministic-draft-leak|null-stale-audio|scrub-audio-base64-meta|passthrough-short|script-validated|all)}
        {--dry-run : Show what would change without writing}
        {--limit=500 : Max rows to update per run (applied per sub-pass)}';

    protected $description = 'Fix s2s_outputs rows misclassified as translation_degraded (empty-source STT outages, deterministic-draft leaks, short identity passthroughs, script-validated translations), null stale audio_output_path, and scrub dead audio_base64_meta blobs on previously backfilled rows.';

    /**
     * Iter 23 — Unicode script ranges keyed by target_lang base (no
     * '-IN' suffix, lowercased). PHP port of the ml-gateway helper at
     * app/adapters/indictrans2.py:_SCRIPT_RANGES. 'sd' (Sindhi) is
     * intentionally omitted because it is dual-script (Arabic in
     * Pakistan, Devanagari in India); those rows fall through.
     */
    private const SCRIPT_RANGES = [
        'hi' => [0x0900, 0x097F], 'mr' => [0x0900, 0x097F], 'sa' => [0x0900, 0x097F],
        'kok' => [0x0900, 0x097F], 'ne' => [0x0900, 0x097F],
        'bn' => [0x0980, 0x09FF], 'as' => [0x0980, 0x09FF],
        'ta' => [0x0B80, 0x0BFF], 'te' => [0x0C00, 0x0C7F],
        'kn' => [0x0C80, 0x0CFF], 'ml' => [0x0D00, 0x0D7F],
        'gu' => [0x0A80, 0x0AFF], 'pa' => [0x0A00, 0x0A7F],
        'od' => [0x0B00, 0x0B7F], 'or' => [0x0B00, 0x0B7F],
        'ur' => [0x0600, 0x06FF],
    ];

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        $dry = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $allowed = [self::MODE_EMPTY_SOURCE, self::MODE_DRAFT_LEAK, self::MODE_NULL_AUDIO, self::MODE_SCRUB_AUDIO_META, self::MODE_PASSTHROUGH_SHORT, self::MODE_SCRIPT_VALIDATED, self::MODE_ALL];
        if (! in_array($mode, $allowed, true)) {
            $this->error(sprintf('Invalid --mode=%s. Allowed: %s', $mode, implode(', ', $allowed)));
            return self::INVALID;
        }

        $totalUpdated = 0;

        if ($mode === self::MODE_EMPTY_SOURCE || $mode === self::MODE_ALL) {
            $this->info('== Pass: empty-source ==');
            $totalUpdated += $this->runEmptySource($dry, $limit);
        }

        if ($mode === self::MODE_DRAFT_LEAK || $mode === self::MODE_ALL) {
            $this->info('== Pass: deterministic-draft-leak ==');
            $totalUpdated += $this->runDeterministicDraftLeak($dry, $limit);
        }

        if ($mode === self::MODE_PASSTHROUGH_SHORT || $mode === self::MODE_ALL) {
            $this->info('== Pass: passthrough-short ==');
            $totalUpdated += $this->runPassthroughShort($dry, $limit);
        }

        if ($mode === self::MODE_SCRIPT_VALIDATED || $mode === self::MODE_ALL) {
            $this->info('== Pass: script-validated ==');
            $totalUpdated += $this->runScriptValidated($dry, $limit);
        }

        if ($mode === self::MODE_NULL_AUDIO || $mode === self::MODE_ALL) {
            $this->info('== Pass: null-stale-audio ==');
            $totalUpdated += $this->runNullStaleAudio($dry, $limit);
        }

        if ($mode === self::MODE_SCRUB_AUDIO_META || $mode === self::MODE_ALL) {
            $this->info('== Pass: scrub-audio-base64-meta ==');
            $totalUpdated += $this->runScrubAudioBase64Meta($dry, $limit);
        }

        $this->info(sprintf('Total rows updated across passes: %d', $totalUpdated));
        return self::SUCCESS;
    }

    /**
     * Iter 17 / 18d original pass — segments where STT produced no
     * source_text and text_output is empty.
     */
    private function runEmptySource(bool $dry, int $limit): int
    {
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->join('s2s_segments as s', 's.id', '=', 'o.segment_id')
            ->where('o.status', 'translation_degraded')
            ->where(function ($q) {
                $q->whereNull('o.text_output')->orWhere('o.text_output', '');
            })
            ->where(function ($q) {
                $q->whereNull('s.source_text')->orWhere('s.source_text', '');
            })
            ->where('o.created_at', '<', now()->subHour())
            ->select('o.*')
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d candidate row(s) for empty-source backfill (limit=%d, dry-run=%s).',
            $candidates->count(),
            $limit,
            $dry ? 'true' : 'false',
        ));

        if ($candidates->isEmpty()) {
            return 0;
        }

        foreach ($candidates as $row) {
            $prevProvider = data_get($row->output_meta, 'provider_payload.provider_used', 'null');
            $this->line(sprintf('  output_id=%d segment_id=%s lang=%s status=%s -> provider_pending  provider_used=%s -> stt_unavailable',
                $row->id,
                $row->segment_id ?? 'null',
                $row->language_code,
                $row->status,
                (string) $prevProvider,
            ));
        }

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $row) {
                $meta = is_array($row->output_meta) ? $row->output_meta : [];
                $providerPayload = is_array($meta['provider_payload'] ?? null) ? $meta['provider_payload'] : [];
                $providerPayload['provider_used'] = 'stt_unavailable';
                $providerPayload['status'] = 'provider_pending';
                $meta['provider_payload'] = $providerPayload;
                $meta['backfill'] = [
                    'reason' => 'iter17_misclassified_translation_degraded',
                    'previous_status' => $row->status,
                    'rewritten_at' => now()->toIso8601String(),
                ];

                $row->status = 'provider_pending';
                $row->output_meta = $meta;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in empty-source pass.', $updated));
        return $updated;
    }

    /**
     * Iter 19 — rows still tagged translation_degraded whose translation
     * came from the IndicTrans2 deterministic draft path even though
     * STT never produced real source_text. Signature:
     *   - status = translation_degraded
     *   - text_output is non-empty (otherwise empty-source pass handles it)
     *   - EITHER provider_response.provider_used = 'sarvam_stt_unavailable'
     *     OR text_output literally contains '[IndicTrans2 deterministic draft'
     */
    private function runDeterministicDraftLeak(bool $dry, int $limit): int
    {
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->where('o.status', 'translation_degraded')
            ->whereNotNull('o.text_output')
            ->where('o.text_output', '!=', '')
            ->where(function ($q) {
                $q->whereRaw("o.output_meta->'provider_response'->>'provider_used' = ?", ['sarvam_stt_unavailable'])
                  ->orWhere('o.text_output', 'like', '%[IndicTrans2 deterministic draft%');
            })
            ->where('o.created_at', '<', now()->subHour())
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d candidate row(s) for deterministic-draft-leak backfill (limit=%d, dry-run=%s).',
            $candidates->count(),
            $limit,
            $dry ? 'true' : 'false',
        ));

        if ($candidates->isEmpty()) {
            return 0;
        }

        foreach ($candidates as $row) {
            $responseProvider = data_get($row->output_meta, 'provider_response.provider_used', 'null');
            $payloadProvider = data_get($row->output_meta, 'provider_payload.provider_used', 'null');
            $preview = mb_substr((string) $row->text_output, 0, 40);
            $this->line(sprintf('  output_id=%d lang=%s status=%s payload=%s response=%s text="%s..." -> provider_pending  provider_used=no_translation_model',
                $row->id,
                $row->language_code,
                $row->status,
                (string) $payloadProvider,
                (string) $responseProvider,
                $preview,
            ));
        }

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $row) {
                $meta = is_array($row->output_meta) ? $row->output_meta : [];
                $providerPayload = is_array($meta['provider_payload'] ?? null) ? $meta['provider_payload'] : [];
                $previousPayloadProvider = $providerPayload['provider_used'] ?? null;
                $previousText = $row->text_output;

                $providerPayload['provider_used'] = 'no_translation_model';
                $providerPayload['status'] = 'provider_pending';
                $providerPayload['text_output'] = null;
                $meta['provider_payload'] = $providerPayload;
                $meta['backfill'] = [
                    'reason' => 'iter19_deterministic_draft_leak',
                    'previous_status' => $row->status,
                    'previous_provider_used' => $previousPayloadProvider,
                    'previous_text_output_len' => $previousText !== null ? mb_strlen((string) $previousText) : 0,
                    'rewritten_at' => now()->toIso8601String(),
                ];

                $row->status = 'provider_pending';
                $row->text_output = null;
                $row->output_meta = $meta;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in deterministic-draft-leak pass.', $updated));
        return $updated;
    }

    /**
     * Iter 19 — rows previously backfilled by THIS command in any
     * prior run that still carry a non-null audio_output_path. The
     * status flipped to provider_pending so the UI shouldn't be
     * playing audio at all; null the path so a stale URL can't be
     * dereferenced.
     */
    private function runNullStaleAudio(bool $dry, int $limit): int
    {
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->where('o.status', 'provider_pending')
            ->whereNotNull('o.audio_output_path')
            ->where('o.audio_output_path', '!=', '')
            ->whereRaw("o.output_meta->'backfill' is not null")
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d candidate row(s) for null-stale-audio (limit=%d, dry-run=%s).',
            $candidates->count(),
            $limit,
            $dry ? 'true' : 'false',
        ));

        if ($candidates->isEmpty()) {
            return 0;
        }

        foreach ($candidates as $row) {
            $this->line(sprintf('  output_id=%d lang=%s status=%s audio_output_path="%s" -> null',
                $row->id,
                $row->language_code,
                $row->status,
                mb_substr((string) $row->audio_output_path, 0, 80),
            ));
        }

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $row) {
                $meta = is_array($row->output_meta) ? $row->output_meta : [];
                $backfill = is_array($meta['backfill'] ?? null) ? $meta['backfill'] : [];
                $backfill['audio_nulled_at'] = now()->toIso8601String();
                $backfill['previous_audio_output_path'] = $row->audio_output_path;
                $meta['backfill'] = $backfill;

                $row->audio_output_path = null;
                $row->output_meta = $meta;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in null-stale-audio pass.', $updated));
        return $updated;
    }

    /**
     * Iter 20 — rows where Sarvam translate (or the IndicTrans2 fallback)
     * echoed a short source fragment verbatim and the pipeline mis-flagged
     * the row as translation_degraded. Examples found by iter 19e:
     *   - "Smooth." → "Smooth." under pa-IN target
     *   - "So,"    → "So,"    under hi-IN target
     *   - "சரி"    → "சரி"    under pa-IN target
     * These are benign passthroughs on single-word/punctuation fragments
     * the translator can't render; the iter 20 ml-gateway patch now emits
     * provider_used='passthrough_short' + status='ready' for future rows.
     * This pass rewrites the existing 'translation_degraded' rows to
     * status='ready' + provider_used='passthrough_short' so they stop
     * surfacing the misleading degraded badge in the UI.
     *
     * Criteria (ALL must hold):
     *   - s2s_outputs.status = 'translation_degraded'
     *   - trim(seg.source_text) = trim(o.text_output) (case-insensitive)
     *   - length(trim(source_text)) <= 8 OR token count <= 2
     *   - both source_text and text_output are non-empty
     *   - row is older than 1 hour (don't touch in-flight rows)
     */
    private function runPassthroughShort(bool $dry, int $limit): int
    {
        // Post-filter the ≤2-tokens-OR-≤8-chars rule in PHP — Postgres regex
        // for "whitespace-separated token count" is fiddly and the candidate
        // set after the trim-equality filter is small enough that loading
        // 100s of rows costs nothing. SQL pre-filters on the cheap predicates
        // (status, non-null, trim-equality, age) to keep the scan bounded.
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->join('s2s_segments as s', 's.id', '=', 'o.segment_id')
            ->where('o.status', 'translation_degraded')
            ->whereNotNull('o.text_output')
            ->where('o.text_output', '!=', '')
            ->whereNotNull('s.source_text')
            ->where('s.source_text', '!=', '')
            ->whereRaw('lower(btrim(s.source_text)) = lower(btrim(o.text_output))')
            ->where('o.created_at', '<', now()->subHour())
            ->select('o.*', 's.source_text as _seg_source_text')
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        // Apply the ≤8-chars OR ≤2-tokens token-count filter in PHP.
        $filtered = $candidates->filter(function ($row): bool {
            $src = trim((string) ($row->_seg_source_text ?? ''));
            if ($src === '') {
                return false;
            }
            if (mb_strlen($src) <= 8) {
                return true;
            }
            $tokens = preg_split('/\s+/u', $src, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return count($tokens) <= 2;
        })->values();

        $this->info(sprintf('Found %d candidate row(s) for passthrough-short backfill (limit=%d, dry-run=%s, pre-filter=%d).',
            $filtered->count(),
            $limit,
            $dry ? 'true' : 'false',
            $candidates->count(),
        ));

        if ($filtered->isEmpty()) {
            return 0;
        }

        foreach ($filtered as $row) {
            $src = trim((string) ($row->_seg_source_text ?? ''));
            $out = trim((string) $row->text_output);
            $this->line(sprintf('  output_id=%d lang=%s status=%s src="%s" out="%s" len=%d -> ready  provider_used=passthrough_short',
                $row->id,
                $row->language_code,
                $row->status,
                mb_substr($src, 0, 30),
                mb_substr($out, 0, 30),
                mb_strlen($src),
            ));
        }

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($filtered, &$updated) {
            foreach ($filtered as $row) {
                $meta = is_array($row->output_meta) ? $row->output_meta : [];
                $providerPayload = is_array($meta['provider_payload'] ?? null) ? $meta['provider_payload'] : [];
                $previousPayloadProvider = $providerPayload['provider_used'] ?? null;

                $providerPayload['provider_used'] = 'passthrough_short';
                $providerPayload['status'] = 'ready';
                $meta['provider_payload'] = $providerPayload;
                $meta['backfill'] = [
                    'reason' => 'iter20_passthrough_short',
                    'previous_status' => $row->status,
                    'previous_provider_used' => $previousPayloadProvider,
                    'rewritten_at' => now()->toIso8601String(),
                ];

                $row->status = 'ready';
                $row->output_meta = $meta;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in passthrough-short pass.', $updated));
        return $updated;
    }

    /**
     * Iter 19e found that backfilled s2s_outputs rows still carry
     * ~3 MB cumulative of dead `audio_base64_meta` blobs inside
     * output_meta — both at output_meta.provider_payload.audio_base64_meta
     * AND nested inside output_meta.provider_response.outputs[*].
     * The audio path itself was nulled by the null-stale-audio pass,
     * so the sha256/bytes_base64/omitted triplet is pure cruft.
     *
     * Surgically remove every `audio_base64_meta` key anywhere in
     * the output_meta tree, leaving the rest (translation result,
     * provider_used, source_text, etc.) untouched. Stamp the audit
     * trail at output_meta.backfill.scrubbed_audio_base64_meta_at.
     */
    private function runScrubAudioBase64Meta(bool $dry, int $limit): int
    {
        // Match only rows where the actual JSON key `"audio_base64_meta":`
        // appears as a key (followed by colon) — not the substring inside our
        // own audit stamp `scrubbed_audio_base64_meta_at`. Belt-and-braces:
        // also skip rows whose backfill.scrubbed_audio_base64_meta_at is set.
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->whereRaw('o.output_meta::text ilike ?', ['%"audio_base64_meta":%'])
            ->whereRaw("(o.output_meta->'backfill'->>'scrubbed_audio_base64_meta_at') is null")
            ->where('o.created_at', '<', now()->subHour())
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d candidate row(s) for scrub-audio-base64-meta (limit=%d, dry-run=%s).',
            $candidates->count(),
            $limit,
            $dry ? 'true' : 'false',
        ));

        if ($candidates->isEmpty()) {
            return 0;
        }

        $totalBytesSaved = 0;
        $previews = [];
        foreach ($candidates as $row) {
            $before = is_array($row->output_meta) ? $row->output_meta : [];
            $after = $this->scrubAudioBase64Meta($before);
            $beforeLen = strlen((string) json_encode($before));
            $afterLen = strlen((string) json_encode($after));
            $delta = max(0, $beforeLen - $afterLen);
            $totalBytesSaved += $delta;
            $previews[] = [
                'id' => $row->id,
                'before' => $beforeLen,
                'after' => $afterLen,
                'delta' => $delta,
            ];
        }

        foreach ($previews as $p) {
            $this->line(sprintf('  output_id=%d  output_meta %d -> %d B (~%d B scrubbed)',
                $p['id'], $p['before'], $p['after'], $p['delta'],
            ));
        }
        $this->info(sprintf('Estimated total bytes scrubbed: %d', $totalBytesSaved));

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $row) {
                $before = is_array($row->output_meta) ? $row->output_meta : [];
                $after = $this->scrubAudioBase64Meta($before);
                $backfill = is_array($after['backfill'] ?? null) ? $after['backfill'] : [];
                $backfill['scrubbed_audio_base64_meta_at'] = now()->toIso8601String();
                $after['backfill'] = $backfill;

                $row->output_meta = $after;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in scrub-audio-base64-meta pass.', $updated));
        return $updated;
    }

    /**
     * Recursively strip every `audio_base64_meta` key from a decoded
     * output_meta tree. Walks arrays-of-arrays so the variant living
     * inside provider_response.outputs[] is caught too.
     */
    private function scrubAudioBase64Meta(array $meta): array
    {
        $walker = function (&$node) use (&$walker): void {
            if (! is_array($node)) {
                return;
            }
            unset($node['audio_base64_meta']);
            foreach ($node as &$child) {
                if (is_array($child)) {
                    $walker($child);
                }
            }
        };
        $walker($meta);
        return $meta;
    }

    /**
     * Iter 23 — s2s_outputs rows still tagged translation_degraded whose
     * text_output is actually in the correct target script. Root cause:
     * the iter-19 IndicTrans2Adapter flipped fallback_used=True whenever
     * the Tijori sidecar metadata smelled fishy (served_via contained
     * "ollama"/"fallback" or model_status said "unavailable") even when
     * the OUTPUT TEXT was correct Devanagari/Tamil/Punjabi/etc. The
     * iter-23 ml-gateway patch script-validates the output and overrides
     * the metadata smell when the script matches; this pass cleans up
     * the ~80 historic rows mis-flagged before that patch landed.
     *
     * Criteria (ALL must hold):
     *   - s2s_outputs.status = 'translation_degraded'
     *   - s2s_outputs.created_at < now() - interval '1 hour'
     *   - text_output is non-empty
     *   - scriptMatchesTarget(text_output, language_code) is True
     *
     * Rewrite to status='ready' and stamp output_meta.backfill.{reason,
     * script_validated_at}. Leave provider_used/text_output untouched.
     */
    private function runScriptValidated(bool $dry, int $limit): int
    {
        $candidates = S2sOutput::query()
            ->from('s2s_outputs as o')
            ->where('o.status', 'translation_degraded')
            ->whereNotNull('o.text_output')
            ->where('o.text_output', '!=', '')
            ->where('o.created_at', '<', now()->subHour())
            ->orderBy('o.id')
            ->limit($limit)
            ->get();

        // Apply the script-validation filter in PHP (Unicode codepoint
        // logic is awkward in pure SQL).
        $filtered = $candidates->filter(function ($row): bool {
            return $this->scriptMatchesTarget(
                (string) $row->text_output,
                (string) $row->language_code,
            );
        })->values();

        $this->info(sprintf('Found %d candidate row(s) for script-validated backfill (limit=%d, dry-run=%s, pre-filter=%d).',
            $filtered->count(),
            $limit,
            $dry ? 'true' : 'false',
            $candidates->count(),
        ));

        if ($filtered->isEmpty()) {
            return 0;
        }

        foreach ($filtered as $row) {
            $preview = mb_substr((string) $row->text_output, 0, 40);
            $this->line(sprintf('  output_id=%d lang=%s status=%s text="%s..." -> ready  reason=iter23_script_validated_translation',
                $row->id,
                $row->language_code,
                $row->status,
                $preview,
            ));
        }

        if ($dry) {
            $this->comment('Dry-run — no rows updated.');
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($filtered, &$updated) {
            foreach ($filtered as $row) {
                $meta = is_array($row->output_meta) ? $row->output_meta : [];
                $backfill = is_array($meta['backfill'] ?? null) ? $meta['backfill'] : [];
                $backfill['reason'] = 'iter23_script_validated_translation';
                $backfill['previous_status'] = $row->status;
                $backfill['script_validated_at'] = now()->toIso8601String();
                $meta['backfill'] = $backfill;

                $row->status = 'ready';
                $row->output_meta = $meta;
                $row->save();
                $updated++;
            }
        });

        $this->info(sprintf('Updated %d row(s) in script-validated pass.', $updated));
        return $updated;
    }

    /**
     * Iter 23 — PHP port of the ml-gateway helper at
     * app/adapters/indictrans2.py:_script_matches_target. Returns True
     * when at least 50% of the alphabetic chars in $text fall within
     * the expected Unicode block for $targetLang. English target passes
     * iff text is mostly ASCII letters. Empty text returns False.
     * Unknown target (e.g. 'sd' Sindhi, dual-script) returns False so
     * the caller doesn't sweep ambiguous rows.
     */
    private function scriptMatchesTarget(string $text, string $targetLang): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        $norm = strtolower(explode('-', $targetLang, 2)[0]);
        if ($norm === 'en') {
            preg_match_all('/\pL/u', $text, $m);
            $alpha = $m[0] ?? [];
            if (empty($alpha)) {
                return false;
            }
            $ascii = 0;
            foreach ($alpha as $c) {
                if (preg_match('/^[A-Za-z]$/', $c)) {
                    $ascii++;
                }
            }
            return ($ascii / count($alpha)) >= 0.5;
        }
        $rng = self::SCRIPT_RANGES[$norm] ?? null;
        if ($rng === null) {
            return false;
        }
        [$lo, $hi] = $rng;
        // Match every Unicode letter in the string. The in-range
        // codepoint test handles target scripts whose chars are NOT
        // tagged "letter" in PCRE (e.g. some combining marks) by
        // treating in-range codepoints as alphabetic for this count.
        preg_match_all('/./u', $text, $m);
        $chars = $m[0] ?? [];
        $alpha = [];
        foreach ($chars as $c) {
            $cp = mb_ord($c, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            if (preg_match('/\pL/u', $c) || ($cp >= $lo && $cp <= $hi)) {
                $alpha[] = $c;
            }
        }
        if (empty($alpha)) {
            return false;
        }
        $inRange = 0;
        foreach ($alpha as $c) {
            $cp = mb_ord($c, 'UTF-8');
            if ($cp !== false && $cp >= $lo && $cp <= $hi) {
                $inRange++;
            }
        }
        return ($inRange / count($alpha)) >= 0.5;
    }
}
