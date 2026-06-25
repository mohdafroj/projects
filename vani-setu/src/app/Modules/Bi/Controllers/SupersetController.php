<?php

namespace App\Modules\Bi\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bi\Services\SupersetService;
use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SupersetController extends Controller
{
    public function sgMis(Request $request, SupersetService $superset, AuditLogger $audit): JsonResponse|array
    {
        try {
            $payload = $superset->sgMisEmbedPayload($request->user());
        } catch (Throwable $exception) {
            return response()->json([
                'configured' => false,
                'dashboard_id' => null,
                'superset_domain' => rtrim((string) config('services.superset.public_url', 'https://garuda.rajyasabha.digital'), '/'),
                'ui_url' => rtrim((string) config('services.superset.public_url', 'https://garuda.rajyasabha.digital'), '/'),
                'guest_token' => null,
                'reason' => $exception->getMessage(),
            ], 503);
        }

        $audit->log('bi.superset.sg_mis.embed', null, [
            'configured' => $payload['configured'],
            'dashboard_id' => $payload['dashboard_id'],
        ]);

        return $payload;
    }
}
