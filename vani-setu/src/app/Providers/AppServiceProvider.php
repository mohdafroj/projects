<?php

namespace App\Providers;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Capture\Policies\BlockPolicy;
use App\Modules\Capture\Policies\SlotAssignmentPolicy;
use App\Modules\Capture\Policies\SlotPolicy;
use App\Modules\Core\Services\Iam\IamAdapter;
use App\Modules\Core\Services\Iam\LocalIamAdapter;
use App\Modules\Core\Services\Iam\SbIamAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Switch LocalIamAdapter to SbIamAdapter here when sb-iam is ready.
        $this->app->bind(IamAdapter::class, LocalIamAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }

        Gate::policy(Block::class, BlockPolicy::class);
        Gate::policy(Slot::class, SlotPolicy::class);
        Gate::policy(SlotAssignment::class, SlotAssignmentPolicy::class);

        RateLimiter::for('auth-login', function (Request $request) {
            $employeeId = Str::lower((string) $request->input('employee_id', ''));

            return Limit::perMinute(5)->by($request->ip().'|'.$employeeId);
        });

        RateLimiter::for('realtime-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('public-workflow-write', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
