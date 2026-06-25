<?php

namespace App\Modules\Bi\Services;

use App\Modules\Core\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupersetService
{
    public function sgMisEmbedPayload(User $user): array
    {
        $publicUrl = $this->publicUrl();
        $dashboardId = (string) config('services.superset.dashboards.sg_mis', '');

        if ($dashboardId === '') {
            return [
                'configured' => false,
                'dashboard_id' => null,
                'superset_domain' => $publicUrl,
                'ui_url' => $publicUrl,
                'guest_token' => null,
                'reason' => 'SUPERSET_SG_MIS_DASHBOARD_ID is not configured yet.',
            ];
        }

        return [
            'configured' => true,
            'dashboard_id' => $dashboardId,
            'superset_domain' => $publicUrl,
            'ui_url' => $publicUrl,
            'guest_token' => $this->guestToken($user, $dashboardId),
            'reason' => null,
        ];
    }

    private function guestToken(User $user, string $dashboardId): string
    {
        $cookies = new CookieJar();
        $csrf = $this->csrfToken($cookies);

        $response = $this->client($cookies)
            ->withToken($this->accessToken())
            ->withHeaders([
                'X-CSRFToken' => $csrf,
                'Referer' => $this->publicUrl().'/',
            ])
            ->post('/api/v1/security/guest_token/', [
                'user' => [
                    'username' => (string) ($user->employee_id ?: $user->id),
                    'first_name' => (string) ($user->name ?: 'SDS'),
                    'last_name' => 'User',
                ],
                'resources' => [
                    [
                        'type' => 'dashboard',
                        'id' => $dashboardId,
                    ],
                ],
                'rls' => [],
            ])
            ->throw()
            ->json();

        $token = (string) ($response['token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Superset guest token response did not include a token.');
        }

        return $token;
    }

    private function csrfToken(CookieJar $cookies): string
    {
        $response = $this->client($cookies)
            ->withToken($this->accessToken())
            ->get('/api/v1/security/csrf_token/')
            ->throw()
            ->json();

        $token = (string) ($response['result'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Superset CSRF token response did not include a token.');
        }

        return $token;
    }

    private function accessToken(): string
    {
        return Cache::remember('superset.api.access_token', now()->addMinutes(10), function (): string {
            $username = (string) config('services.superset.username', '');
            $password = (string) config('services.superset.password', '');

            if ($username === '' || $password === '') {
                throw new RuntimeException('Superset API credentials are not configured.');
            }

            $response = $this->client()
                ->post('/api/v1/security/login', [
                    'username' => $username,
                    'password' => $password,
                    'provider' => 'db',
                    'refresh' => true,
                ])
                ->throw()
                ->json();

            $token = (string) ($response['access_token'] ?? '');

            if ($token === '') {
                throw new RuntimeException('Superset login response did not include an access token.');
            }

            return $token;
        });
    }

    private function client(?CookieJar $cookies = null)
    {
        return Http::baseUrl($this->internalUrl())
            ->acceptJson()
            ->asJson()
            ->withOptions($cookies ? ['cookies' => $cookies] : [])
            ->timeout((int) config('services.superset.timeout', 10));
    }

    private function internalUrl(): string
    {
        return rtrim((string) config('services.superset.internal_url', 'http://sds-reporting-superset:8088'), '/');
    }

    private function publicUrl(): string
    {
        return rtrim((string) config('services.superset.public_url', 'https://garuda.rajyasabha.digital'), '/');
    }
}
