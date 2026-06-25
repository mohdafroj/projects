<?php

namespace App\Modules\Sg\Services;

use App\Modules\Js\Models\JsWindow;
use Carbon\CarbonInterface;

interface DscAdapter
{
    /**
     * @return array{serial: string, signed_at: CarbonInterface}
     */
    public function sign(JsWindow $window): array;
}
