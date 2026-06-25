<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        app()->instance('request.id', $requestId);
        app()->instance('request.ip', $request->ip());
        app()->instance('request.ua', (string) $request->userAgent());
        app()->instance('audit.actor_id', fn () => $request->user()?->getAuthIdentifier());
        app()->instance('audit.actor_role', function () use ($request) {
            $user = $request->user();

            if (! $user) {
                return 'system';
            }

            return $user->roles()->value('name') ?? 'authenticated';
        });

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
