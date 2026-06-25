<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModuleInventoryTest extends TestCase
{
    #[DataProvider('modules')]
    public function test_controller_directory_has_php_files(string $module): void
    {
        $this->assertDirectoryHasPhpFiles(base_path("app/Modules/{$module}/Controllers"));
    }

    #[DataProvider('modules')]
    public function test_model_directory_has_php_files(string $module): void
    {
        $this->assertDirectoryHasPhpFiles(base_path("app/Modules/{$module}/Models"));
    }

    #[DataProvider('modules')]
    public function test_migration_directory_has_php_files(string $module): void
    {
        $this->assertDirectoryHasPhpFiles(base_path("app/Modules/{$module}/Migrations"));
    }

    #[DataProvider('modules')]
    public function test_routes_file_exposes_at_least_five_routes(string $module): void
    {
        $routes = file_get_contents(base_path("app/Modules/{$module}/routes-api.php"));

        $this->assertGreaterThanOrEqual(5, preg_match_all('/Route::(get|post|put|patch|delete)\(/', $routes));
    }

    #[DataProvider('modules')]
    public function test_feature_test_directory_has_php_tests(string $module): void
    {
        $this->assertDirectoryHasPhpFiles(base_path("tests/Feature/{$module}"));
    }

    #[DataProvider('modules')]
    public function test_module_manifest_exists(string $module): void
    {
        $this->assertFileExists(base_path("app/Modules/{$module}/module.json"));
    }

    #[DataProvider('modules')]
    public function test_module_has_seeders(string $module): void
    {
        $this->assertDirectoryHasPhpFiles(base_path("app/Modules/{$module}/Seeders"));
    }

    #[DataProvider('modulePrefixes')]
    public function test_routes_are_declared_under_expected_module_prefix(string $module, string $prefix): void
    {
        $routes = file_get_contents(base_path("app/Modules/{$module}/routes-api.php"));

        $this->assertStringContainsString("Route::prefix('{$prefix}')", $routes);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function modules(): array
    {
        return [
            'chief' => ['Chief'],
            'js' => ['Js'],
            'sg' => ['Sg'],
            'director' => ['Director'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function modulePrefixes(): array
    {
        return [
            'chief' => ['Chief', 'chief'],
            'js' => ['Js', 'js'],
            'sg' => ['Sg', 'sg'],
            'director' => ['Director', 'director'],
        ];
    }

    private function assertDirectoryHasPhpFiles(string $path): void
    {
        $this->assertDirectoryExists($path);
        $this->assertNotEmpty(glob($path.'/*.php') ?: [], "Expected PHP files in {$path}.");
    }
}
