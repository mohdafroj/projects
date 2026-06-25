<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\Reports\Models\ReportSnapshot;
use App\Modules\Reports\Requests\ReportFilterRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function summary(ReportFilterRequest $request, AuditLogger $audit)
    {
        $filters = $request->filters();
        $summary = $this->summaryData($filters);

        $audit->log('reports.summary.viewed', null, [
            'filters' => $filters,
            'block_count' => $summary['totals']['blocks'],
        ]);

        return $summary;
    }

    public function charts(ReportFilterRequest $request, AuditLogger $audit)
    {
        $filters = $request->filters();
        $charts = $this->chartData($filters);

        $audit->log('reports.charts.viewed', null, [
            'filters' => $filters,
            'series' => array_keys($charts),
        ]);

        return $charts;
    }

    public function captureSnapshot(ReportFilterRequest $request, AuditLogger $audit)
    {
        $filters = $request->filters();
        $charts = $this->chartData($filters);

        $snapshot = ReportSnapshot::query()->create([
            'name' => $request->validated('name') ?: 'Reports snapshot '.now()->format('Y-m-d H:i'),
            'filters' => $filters,
            'chart_data' => $charts,
            'export_meta' => [
                'summary' => $this->summaryData($filters)['totals'],
            ],
            'captured_by_user_id' => $request->user()->id,
            'captured_at' => now(),
        ]);

        $auditLog = $audit->log('reports.snapshot.captured', $snapshot, [
            'filters' => $filters,
            'series' => array_keys($charts),
        ]);

        $snapshot->forceFill(['captured_audit_log_id' => $auditLog->id])->save();

        return response()->json($snapshot->fresh(), 201);
    }

    public function export(ReportFilterRequest $request, AuditLogger $audit): Response
    {
        $filters = $request->filters();
        $format = $request->validated('format') ?: 'csv';
        $rows = $this->exportRows($filters);

        $audit->log("reports.export.{$format}", null, [
            'filters' => $filters,
            'row_count' => count($rows),
        ]);

        if ($format === 'pdf') {
            return response($this->pdfPayload($filters, $rows), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="reports.pdf"',
            ]);
        }

        return response($this->csvPayload($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reports.csv"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summaryData(array $filters): array
    {
        $query = $this->filteredBlocks($filters);

        $totals = (clone $query)
            ->selectRaw('COUNT(blocks.id) AS blocks')
            ->selectRaw('COUNT(DISTINCT slots.id) AS slots')
            ->selectRaw('COUNT(DISTINCT sittings.id) AS sittings')
            ->selectRaw('COALESCE(SUM(blocks.end_ms - blocks.start_ms), 0) AS duration_ms')
            ->first();

        return [
            'filters' => $filters,
            'totals' => [
                'blocks' => (int) $totals->blocks,
                'slots' => (int) $totals->slots,
                'sittings' => (int) $totals->sittings,
                'duration_ms' => (int) $totals->duration_ms,
            ],
            'coverage' => [
                'users' => $this->assignmentDimension($filters, 'users.name'),
                'sections' => $this->assignmentDimension($filters, 'users.section'),
                'workflow' => $this->assignmentDimension($filters, 'slot_assignments.workflow_stage'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function chartData(array $filters): array
    {
        return [
            'by_date' => $this->dimension($filters, 'sittings.sitting_date', 'sittings.sitting_date'),
            'by_language' => $this->dimension($filters, 'blocks.original_lang', 'blocks.original_lang'),
            'by_content_type' => $this->contentTypeSeries($filters),
            'by_workflow' => $this->assignmentDimension($filters, 'slot_assignments.workflow_stage'),
            'by_section' => $this->assignmentDimension($filters, 'users.section'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredBlocks(array $filters): Builder
    {
        $query = Block::query()
            ->join('slots', 'slots.id', '=', 'blocks.slot_id')
            ->join('sittings', 'sittings.id', '=', 'slots.sitting_id')
            ->leftJoin('members', 'members.id', '=', 'blocks.member_id');

        if ($filters['date_from']) {
            $query->whereDate('sittings.sitting_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('sittings.sitting_date', '<=', $filters['date_to']);
        }

        if ($filters['content_type'] === 'original') {
            $query->whereNotNull('blocks.text');
        } elseif ($filters['content_type'] === 'ai') {
            $query->whereNotNull('blocks.ai_text');
        } elseif ($filters['content_type'] === 'translated') {
            $query->whereNotNull('blocks.translated_text');
        }

        $this->applyAssignmentFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAssignmentFilters(Builder $query, array $filters): void
    {
        $workflowStages = $filters['workflow_stage'];
        $userIds = $filters['user_id'];
        $sections = $filters['section'];

        if ($workflowStages === [] && $userIds === [] && $sections === []) {
            return;
        }

        $query->whereExists(function ($subQuery) use ($workflowStages, $userIds, $sections) {
            $subQuery->selectRaw('1')
                ->from('slot_assignments')
                ->join('users', 'users.id', '=', 'slot_assignments.user_id')
                ->whereColumn('slot_assignments.slot_id', 'slots.id');

            if ($workflowStages !== []) {
                $subQuery->whereIn('slot_assignments.workflow_stage', $workflowStages);
            }

            if ($userIds !== []) {
                $subQuery->whereIn('slot_assignments.user_id', $userIds);
            }

            if ($sections !== []) {
                $subQuery->whereIn('users.section', $sections);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, blocks: int, slots: int}>
     */
    private function dimension(array $filters, string $labelColumn, string $orderColumn): array
    {
        return (clone $this->filteredBlocks($filters))
            ->selectRaw("{$labelColumn} AS label")
            ->selectRaw('COUNT(blocks.id) AS blocks')
            ->selectRaw('COUNT(DISTINCT slots.id) AS slots')
            ->groupBy(DB::raw($labelColumn))
            ->orderBy(DB::raw($orderColumn))
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'blocks' => (int) $row->blocks,
                'slots' => (int) $row->slots,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, blocks: int, slots: int}>
     */
    private function assignmentDimension(array $filters, string $labelColumn): array
    {
        $labelExpression = "COALESCE({$labelColumn}, 'Unassigned')";

        return Slot::query()
            ->join('sittings', 'sittings.id', '=', 'slots.sitting_id')
            ->join('blocks', 'blocks.slot_id', '=', 'slots.id')
            ->join('slot_assignments', 'slot_assignments.slot_id', '=', 'slots.id')
            ->join('users', 'users.id', '=', 'slot_assignments.user_id')
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('sittings.sitting_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('sittings.sitting_date', '<=', $date))
            ->when($filters['workflow_stage'] !== [], fn ($query) => $query->whereIn('slot_assignments.workflow_stage', $filters['workflow_stage']))
            ->when($filters['user_id'] !== [], fn ($query) => $query->whereIn('slot_assignments.user_id', $filters['user_id']))
            ->when($filters['section'] !== [], fn ($query) => $query->whereIn('users.section', $filters['section']))
            ->when($filters['content_type'] === 'translated', fn ($query) => $query->whereNotNull('blocks.translated_text'))
            ->selectRaw("{$labelExpression} AS label")
            ->selectRaw('COUNT(DISTINCT blocks.id) AS blocks')
            ->selectRaw('COUNT(DISTINCT slots.id) AS slots')
            ->groupBy(DB::raw($labelExpression))
            ->orderBy(DB::raw($labelExpression))
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'blocks' => (int) $row->blocks,
                'slots' => (int) $row->slots,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, blocks: int}>
     */
    private function contentTypeSeries(array $filters): array
    {
        $query = $this->filteredBlocks([...$filters, 'content_type' => 'all']);

        return [
            ['label' => 'original', 'blocks' => (int) (clone $query)->whereNotNull('blocks.text')->count('blocks.id')],
            ['label' => 'ai', 'blocks' => (int) (clone $query)->whereNotNull('blocks.ai_text')->count('blocks.id')],
            ['label' => 'translated', 'blocks' => (int) (clone $query)->whereNotNull('blocks.translated_text')->count('blocks.id')],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportRows(array $filters): array
    {
        return $this->filteredBlocks($filters)
            ->select('blocks.*')
            ->with(['slot.sitting', 'member', 'customMember'])
            ->orderBy('sittings.sitting_date')
            ->orderBy('slots.code')
            ->orderBy('blocks.sequence')
            ->limit(5000)
            ->get()
            ->map(fn (Block $block) => [
                'sitting_date' => $block->slot->sitting->sitting_date->toDateString(),
                'slot_code' => $block->slot->code,
                'sequence' => $block->sequence,
                'workflow_stage' => $block->slot->overallWorkflowStage(),
                'speaker' => $block->member?->name_en ?? $block->customMember?->name_en ?? '',
                'original_lang' => $block->original_lang,
                'chief_lang' => $block->chief_lang,
                'text' => $block->translated_text ?: $block->text,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function csvPayload(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        $headers = ['sitting_date', 'slot_code', 'sequence', 'workflow_stage', 'speaker', 'original_lang', 'chief_lang', 'text'];
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, mixed>>  $rows
     */
    private function pdfPayload(array $filters, array $rows): string
    {
        $title = 'Vani Setu Reports';
        $body = $title."\nFilters: ".json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\nRows: ".count($rows);

        return "%PDF-1.4\n% Vani Setu report export\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj\n"
            .'3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >> endobj'."\n"
            .'4 0 obj << /Length '.strlen($body).' >> stream'."\n{$body}\nendstream endobj\n%%EOF\n";
    }
}
