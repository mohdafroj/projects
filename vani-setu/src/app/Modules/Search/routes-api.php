<?php

use App\Modules\Core\Services\Audit\AuditLogger;
use App\Modules\CommitteeSittings\Models\CommitteeParticipant;
use App\Modules\Search\Services\SearchIndexer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/search/artifacts', function (Request $request, SearchIndexer $indexer, AuditLogger $audit) {
    $validated = $request->validate([
        'q' => ['nullable', 'string', 'max:500'],
        'filters' => ['nullable'],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $filters = $validated['filters'] ?? [];
    if (is_string($filters)) {
        $decoded = json_decode($filters, true);
        $filters = is_array($decoded) ? $decoded : [];
    }
    if (! is_array($filters)) {
        $filters = [];
    }

    $query = trim((string) ($validated['q'] ?? ''));
    $page = (int) ($validated['page'] ?? 1);
    $results = $indexer->searchArtifacts($query, $filters, $page);

    $audit->log('search.artifacts.query', null, [
        'query_length' => mb_strlen($query),
        'filters' => array_keys($filters),
        'page' => $page,
        'estimated_total_hits' => $results['totalHits'] ?? $results['estimatedTotalHits'] ?? null,
    ]);

    return response()->json($results);
});

Route::middleware('auth:sanctum')->get('/search', function (Request $request, SearchIndexer $indexer, AuditLogger $audit) {
    $validated = $request->validate([
        'q' => ['nullable', 'string', 'max:500'],
        'filters' => ['nullable'],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $filters = $validated['filters'] ?? [];
    if (is_string($filters)) {
        $decoded = json_decode($filters, true);
        $filters = is_array($decoded) ? $decoded : [];
    }
    if (! is_array($filters)) {
        $filters = [];
    }

    $wantsInCamera = ! empty($filters['include_in_camera']);
    $committeeId = isset($filters['committee_id']) && is_numeric($filters['committee_id']) ? (int) $filters['committee_id'] : null;
    $canSearchInCamera = $request->user()?->hasRole('admin') || (
        $committeeId !== null
        && CommitteeParticipant::query()
            ->where('committee_id', $committeeId)
            ->where('user_id', $request->user()?->id)
            ->whereIn('role', ['committee_chair', 'committee_secretary', 'committee_secretariat'])
            ->exists()
    );
    if ($wantsInCamera && ! $canSearchInCamera) {
        unset($filters['include_in_camera']);
    }

    $query = trim((string) ($validated['q'] ?? ''));
    $page = (int) ($validated['page'] ?? 1);
    $results = $indexer->search($query, is_array($filters) ? $filters : [], $page);

    $audit->log('search.query', null, [
        'query_length' => mb_strlen($query),
        'filters' => is_array($filters) ? array_keys($filters) : [],
        'page' => $page,
        'estimated_total_hits' => $results['totalHits'] ?? $results['estimatedTotalHits'] ?? null,
    ]);

    return response()->json($results);
});
