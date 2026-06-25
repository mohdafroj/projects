<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    public function test_metrics_endpoint_exposes_http_and_queue_metrics(): void
    {
        $this->getJson('/api/health')->assertOk();

        $response = $this->get('/api/metrics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $response->assertSee('vani_http_requests_total{', false);
        $response->assertSee('route="api/health"', false);
        $response->assertSee('vani_http_request_duration_seconds_bucket{', false);
        $response->assertSee('horizon_queue_depth{queue="default"}', false);
        $response->assertSee('vani_service_dependency_up{service="redis"}', false);
    }
}
