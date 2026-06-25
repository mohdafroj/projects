<?php

namespace App\Http\Controllers;

use App\Support\Metrics\Metrics;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function app(Metrics $metrics): Response
    {
        return response($metrics->render(['app', 'queue', 'dependencies', 's2s']), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    public function horizon(Metrics $metrics): Response
    {
        return response($metrics->render(['queue']), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    public function reverb(Metrics $metrics): Response
    {
        return response($metrics->render(['dependencies']), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
