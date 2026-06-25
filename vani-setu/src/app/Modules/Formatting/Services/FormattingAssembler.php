<?php

namespace App\Modules\Formatting\Services;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\User;
use App\Modules\Formatting\Models\FormattingJob;
use App\Modules\Formatting\Models\FormattingLine;
use App\Modules\Js\Models\JsWindow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormattingAssembler
{
    public const LINES_PER_PAGE = 28;

    /**
     * @return array{metadata: array<string, mixed>, hash: string, lines: list<array<string, mixed>>, page_count: int}
     */
    public function assemble(JsWindow $window, string $artifactType, User $actor): array
    {
        $blocks = $window->blocks()->get();
        $filtered = $this->filterBlocks($blocks, $artifactType)->values();
        $metadata = $this->metadata($window, $artifactType, $actor);
        $lines = $this->lines($filtered, $artifactType);
        $hash = hash('sha256', json_encode([
            'window_id' => $window->id,
            'artifact_type' => $artifactType,
            'metadata' => $metadata,
            'source_blocks' => $filtered->map(fn (Block $block) => [
                'id' => $block->id,
                'version' => $block->version,
                'text' => $block->text,
                'lang' => $block->chief_lang,
            ])->all(),
        ], JSON_THROW_ON_ERROR));

        return [
            'metadata' => $metadata,
            'hash' => $hash,
            'lines' => $lines,
            'page_count' => collect($lines)->max('page_number') ?: 1,
        ];
    }

    /**
     * @return array{ok: bool, errors: list<string>, warnings: list<string>}
     */
    public function validate(FormattingJob $job): array
    {
        $job->loadMissing('lines');
        $errors = [];
        $warnings = [];
        $metadata = $job->metadata ?? [];

        if (($metadata['operator'] ?? null) !== 'Yogesh') {
            $errors[] = 'DVOT-Yogesh metadata is required.';
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $job->crc_source_hash)) {
            $errors[] = 'CRC source hash is missing or invalid.';
        }

        if ($job->lines->isEmpty()) {
            $errors[] = 'At least one formatted line is required.';
        }

        if ($job->lines->contains(fn (FormattingLine $line) => $line->page_number < 1)) {
            $errors[] = 'Every formatted line must carry page numbering.';
        }

        if ($job->artifact_type === 'hv' && $job->lines->contains(fn (FormattingLine $line) => $line->lang === 'en')) {
            $errors[] = 'HV cannot contain English speech lines.';
        }

        if ($job->artifact_type === 'ev' && $job->lines->contains(fn (FormattingLine $line) => $line->lang === 'hi')) {
            $errors[] = 'EV cannot contain Hindi speech lines.';
        }

        if (! $job->lines->contains('kind', 'bifurcation')) {
            $warnings[] = 'No bilingual bifurcation line was needed for this source.';
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    public function renderCrc(FormattingJob $job): string
    {
        $job->loadMissing(['lines', 'sitting']);
        $path = 'formatting/crc/job-'.$job->id.'.html';
        $title = strtoupper($job->artifact_type).' CRC';
        $rows = $job->lines->map(function (FormattingLine $line) {
            $class = e($line->kind);
            $speaker = $line->speaker_label ? '<strong>'.e($line->speaker_label).'</strong> ' : '';

            return '<p class="'.$class.'"><span class="page">Page '.e((string) $line->page_number).'</span> '.$speaker.e((string) $line->body).'</p>';
        })->implode("\n");

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:serif;line-height:1.5;margin:36px}.page{float:right;color:#666}.bifurcation{border-top:1px solid #999;padding-top:8px}.oih,.plot{font-style:italic}</style></head><body><h1>'.e($title).'</h1><p>CRC source '.e($job->crc_source_hash).'</p>'.$rows.'</body></html>';
        Storage::disk('local')->put($path, $html);

        return $path;
    }

    /**
     * @param  Collection<int, Block>  $blocks
     * @return Collection<int, Block>
     */
    private function filterBlocks(Collection $blocks, string $artifactType): Collection
    {
        return match ($artifactType) {
            'hv' => $blocks->where('chief_lang', 'hi'),
            'ev' => $blocks->where('chief_lang', 'en'),
            'synopsis' => $blocks->take(12),
            default => $blocks,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(JsWindow $window, string $artifactType, User $actor): array
    {
        return [
            'source' => 'DVOT-Yogesh',
            'operator' => 'Yogesh',
            'formatter_user_id' => $actor->id,
            'window_code' => $window->window_code,
            'artifact_type' => $artifactType,
            'nomenclature' => ['FV', 'HV', 'EV', 'Synopsis', 'CRC', 'PLOT', 'OIH'],
            'rules_source' => 'Vani Setu SRS v2.0 via pdftotext',
        ];
    }

    /**
     * @param  Collection<int, Block>  $blocks
     * @return list<array<string, mixed>>
     */
    private function lines(Collection $blocks, string $artifactType): array
    {
        $lines = [];
        $sequence = 1;
        $previousLang = null;

        foreach ($blocks as $block) {
            if ($previousLang !== null && $previousLang !== $block->chief_lang) {
                $lines[] = $this->line($sequence++, 'bifurcation', null, null, '--- Language bifurcation: '.$previousLang.' to '.$block->chief_lang.' ---');
            }

            if ($block->original_lang === 'hi') {
                $lines[] = $this->line($sequence++, 'oih', 'hi', null, 'OIH: Original-In-Hindi marker preserved.', $block->id);
            }

            if (Str::contains(Str::lower($block->text), ['paper laid', 'papers laid', 'laid on the table', 'plot'])) {
                $lines[] = $this->line($sequence++, 'plot', $block->chief_lang, null, 'PLOT: Papers Laid on the Table reference retained.', $block->id);
            }

            $speaker = $this->speakerLabel($block);
            $lines[] = $this->line(
                $sequence++,
                'speaker',
                $block->chief_lang,
                $block->chief_lang === 'en' ? Str::upper($speaker) : $speaker,
                $block->chief_lang === 'hi' ? 'Speaker label style: bold Hindi.' : 'Speaker label style: English capitals.',
                $block->id,
                ['speaker_style' => $block->chief_lang === 'hi' ? 'bold' : 'capitals']
            );
            $lines[] = $this->line($sequence++, 'text', $block->chief_lang, null, $artifactType === 'synopsis' ? Str::limit($block->text, 180, '') : $block->text, $block->id);
            $previousLang = $block->chief_lang;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $sequence, string $kind, ?string $lang, ?string $speaker, ?string $body, ?int $blockId = null, array $metadata = []): array
    {
        return [
            'block_id' => $blockId,
            'sequence' => $sequence,
            'kind' => $kind,
            'lang' => $lang,
            'speaker_label' => $speaker,
            'body' => $body,
            'page_number' => max(1, (int) ceil($sequence / self::LINES_PER_PAGE)),
            'metadata' => $metadata,
        ];
    }

    private function speakerLabel(Block $block): string
    {
        $speaker = $block->member ?: $block->customMember;

        if ($speaker) {
            return $block->chief_lang === 'hi' ? ($speaker->name_hi ?? $speaker->name_en) : $speaker->name_en;
        }

        return $block->chief_lang === 'hi' ? 'माननीय सदस्य' : 'Hon. Member';
    }
}
