<?php

namespace App\Modules\Js\Controllers;

use App\Http\Controllers\Controller;

class JsConsoleController extends Controller
{
    public function capabilities(): array
    {
        return [
            'queue',
            'window_review',
            'suggested_edits',
            'expunge_candidates',
            'sg_handoff',
            'director_handoff',
            'returns',
        ];
    }
}
