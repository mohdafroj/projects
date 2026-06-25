<?php

namespace App\Modules\Sg\Services;

use App\Modules\Js\Models\JsWindow;
use RuntimeException;

class CdacEsignAdapter implements DscAdapter
{
    public function sign(JsWindow $window): array
    {
        // TODO: Integrate CDAC eSign once the gateway contract and certificates are provisioned.
        throw new RuntimeException('CDAC eSign adapter is not configured.');
    }
}
