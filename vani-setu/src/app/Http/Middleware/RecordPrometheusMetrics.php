<?php

namespace App\Http\Middleware;

use App\Support\Metrics\Metrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPrometheusMetrics
{
    public function __construct(
        private readonly Metrics $metrics,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);

        $route = $request->route();
        $uri = $route?->uri() ?? $request->path();

        if (str_starts_with($uri, 'api/metrics')) {
            return $response;
        }

        $labels = [
            'method' => $request->method(),
            'route' => $uri,
            'status_code' => (string) $response->getStatusCode(),
            'status_class' => ((int) floor($response->getStatusCode() / 100)).'xx',
        ];

        $this->metrics->counter('vani_http_requests_total', $labels);
        $this->metrics->histogram('vani_http_request_duration_seconds', $labels, microtime(true) - $started);

        return $response;
    }
}
