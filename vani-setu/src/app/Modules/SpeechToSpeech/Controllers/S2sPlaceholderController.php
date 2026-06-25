<?php

namespace App\Modules\SpeechToSpeech\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class S2sPlaceholderController extends Controller
{
    public function create(Request $request, AuditLogger $audit)
    {
        $audit->log('s2s.session.placeholder', null, ['route' => 'create', 'user_id' => $request->user()?->id]);

        return $this->placeholder('session-create');
    }

    public function show(int $id)
    {
        return $this->placeholder('session-show', ['session_id' => $id]);
    }

    public function segments(int $id)
    {
        return $this->placeholder('segments-list', ['session_id' => $id]);
    }

    public function storeSegment(Request $request, int $id, AuditLogger $audit)
    {
        $audit->log('s2s.segment.placeholder', null, ['route' => 'store-segment', 'session_id' => $id]);

        return $this->placeholder('segment-ingest', ['session_id' => $id]);
    }

    public function finish(Request $request, int $id, AuditLogger $audit)
    {
        $audit->log('s2s.session.finish.placeholder', null, ['route' => 'finish', 'session_id' => $id]);

        return $this->placeholder('session-finish', ['session_id' => $id]);
    }

    private function placeholder(string $operation, array $extra = [])
    {
        return response()->json($extra + [
            'status' => 'placeholder',
            'operation' => $operation,
            'planned_engine' => 'sarvam|bhashini|whisper+indictrans2+tts',
            'expected' => 'available in v1.1',
        ], 501);
    }
}
