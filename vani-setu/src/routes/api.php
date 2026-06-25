<?php

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Controllers\MetricsController;
use App\Http\Middleware\RecordPrometheusMetrics;
use App\Modules\Notifications\Controllers\NotificationDispatchController;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Iam\IamAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::connection()->getPdo();

    return response()->json([
        'status' => 'ok',
        'service' => 'vani-setu',
        'checked_at' => now()->toIso8601String(),
    ]);
})->middleware(RecordPrometheusMetrics::class);

Route::get('/metrics', [MetricsController::class, 'app']);

Route::post('/auth/login', function (LoginRequest $request, IamAdapter $iam) {
    $user = $iam->authenticate(
        $request->string('employee_id')->toString(),
        $request->string('password')->toString(),
    );

    if (! $user) {
        return response()->json([
            'message' => 'Invalid credentials.',
        ], 401);
    }

    return response()->json([
        'token' => $user->getAttribute('plain_text_token'),
        'user' => $user,
        'roles' => $user->getRoleNames(),
        'permissions' => $user->getAllPermissions()->pluck('name')->values(),
    ]);
})->middleware('throttle:auth-login');


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/notifications', [NotificationDispatchController::class, 'dispatch']);
    Route::post('/notifications/email', [NotificationDispatchController::class, 'sendEmail']);
    Route::post('/notifications/sms', [NotificationDispatchController::class, 'sendSms']);

    Route::post('/auth/logout', function (LogoutRequest $request, IamAdapter $iam) {
        /** @var User $user */
        $user = $request->user();
        $iam->logout($user);

        return response()->json([
            'message' => 'Logged out.',
        ]);
    });

    Route::get('/me', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/system-health', function () {
            $checks = [];

            $checks[] = checkService('laravel', 'ok', 'Laravel API is responding.');

            try {
                DB::connection()->getPdo();
                $checks[] = checkService('postgres', 'ok', 'Primary database connection is healthy.');
            } catch (Throwable $exception) {
                $checks[] = checkService('postgres', 'fail', $exception->getMessage());
            }

            try {
                Redis::connection()->ping();
                $checks[] = checkService('redis', 'ok', 'Redis ping succeeded.');
            } catch (Throwable $exception) {
                $checks[] = checkService('redis', 'fail', $exception->getMessage());
            }

            foreach ([
                'frontend' => 'http://frontend:5173',
                'ml-gateway' => 'http://ml-gateway:8000/healthz',
                'realtime-sidecar' => 'http://realtime-sidecar:1234/health',
                'meilisearch' => 'http://meilisearch:7700/health',
            ] as $name => $url) {
                $checks[] = httpServiceCheck($name, $url);
            }

            return response()->json([
                'status' => collect($checks)->every(fn ($check) => $check['status'] === 'ok') ? 'ok' : 'degraded',
                'checked_at' => now()->toIso8601String(),
                'services' => $checks,
            ]);
        });

        Route::get('/reverb-metrics', function () {
            try {
                Redis::connection()->ping();

                return response()->json([
                    'status' => 'ok',
                    'transport' => 'reverb',
                    'redis' => 'ok',
                    'checked_at' => now()->toIso8601String(),
                ]);
            } catch (Throwable $exception) {
                return response()->json([
                    'status' => 'fail',
                    'transport' => 'reverb',
                    'redis' => 'fail',
                    'error' => $exception->getMessage(),
                    'checked_at' => now()->toIso8601String(),
                ], 503);
            }
        });
    });
});

if (! function_exists('checkService')) {
    function checkService(string $name, string $status, string $detail): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}

if (! function_exists('httpServiceCheck')) {
    function httpServiceCheck(string $name, string $url): array
    {
        try {
            $response = Http::timeout(2)->get($url);

            return checkService(
                $name,
                $response->successful() ? 'ok' : 'fail',
                "HTTP {$response->status()} {$url}",
            );
        } catch (ConnectionException $exception) {
            return checkService($name, 'fail', $exception->getMessage());
        } catch (Throwable $exception) {
            return checkService($name, 'fail', $exception->getMessage());
        }
    }
}
