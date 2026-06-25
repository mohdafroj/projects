<?php

namespace App\Modules\Sg\Services;

use App\Modules\Js\Models\JsWindow;

class LocalStubDscAdapter implements DscAdapter
{
    public function sign(JsWindow $window): array
    {
        return [
            'serial' => sprintf('DSC-SG-STUB-%06d-%s', $window->id, now()->format('YmdHis')),
            'signed_at' => now(),
        ];
    }
}
