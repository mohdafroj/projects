<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, list<class-string>>
     */
    private static array $seeders = [];

    public function register(): void
    {
        self::$seeders = [];

        foreach ($this->moduleDirectories() as $modulePath) {
            $metadata = $this->moduleMetadata($modulePath);
            $name = $metadata['name'] ?? basename($modulePath);
            $seeders = $metadata['seeders'] ?? [];
            $providers = $metadata['providers'] ?? [];

            self::$seeders[$name] = $seeders;

            foreach ($providers as $provider) {
                $this->app->register($provider);
            }
        }

        ksort(self::$seeders);
    }

    public function boot(): void
    {
        $migrationPaths = [];

        foreach ($this->moduleDirectories() as $modulePath) {
            $routesPath = $modulePath.'/routes-api.php';
            if (is_file($routesPath)) {
                Route::prefix('api')->middleware('api')->group($routesPath);
            }

            $migrationsPath = $modulePath.'/Migrations';
            if (is_dir($migrationsPath)) {
                $migrationPaths[] = $migrationsPath;
            }
        }

        if ($migrationPaths !== []) {
            $this->loadMigrationsFrom($migrationPaths);
        }
    }

    /**
     * @return array<string, list<class-string>>
     */
    public static function seeders(): array
    {
        return self::$seeders;
    }

    /**
     * @return list<string>
     */
    private function moduleDirectories(): array
    {
        $directories = glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [];
        sort($directories);

        return $directories;
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleMetadata(string $modulePath): array
    {
        $metadataPath = $modulePath.'/module.json';
        if (! is_file($metadataPath)) {
            return [];
        }

        return json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
    }
}
