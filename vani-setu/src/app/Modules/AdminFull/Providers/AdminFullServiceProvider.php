<?php

namespace App\Modules\AdminFull\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\RoleMiddleware;

class AdminFullServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);
    }
}
