<?php

namespace App\Modules\Sg\Providers;

use App\Modules\Sg\Services\DscAdapter;
use App\Modules\Sg\Services\LocalStubDscAdapter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\RoleMiddleware;

class SgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DscAdapter::class, LocalStubDscAdapter::class);
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);
    }
}
