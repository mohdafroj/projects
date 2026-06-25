<?php

namespace Tests\Unit;

use App\Support\Safety\ProductionDatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class ProductionDatabaseGuardTest extends TestCase
{
    public function test_blocks_destructive_command_against_protected_production_database(): void
    {
        $guard = new ProductionDatabaseGuard();

        config([
            'app.env' => 'production',
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'vani_setu_prod',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked artisan command [migrate:fresh]');

        $guard->handleCommandStarting(new CommandStarting('migrate:fresh', new ArgvInput([]), new NullOutput()));
    }

    public function test_allows_safe_command_against_protected_production_database(): void
    {
        $guard = new ProductionDatabaseGuard();

        config([
            'app.env' => 'production',
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'vani_setu_prod',
        ]);

        $guard->handleCommandStarting(new CommandStarting('audit:verify', new ArgvInput([]), new NullOutput()));

        $this->assertTrue(true);
    }

    public function test_blocks_seeding_against_protected_production_database(): void
    {
        $guard = new ProductionDatabaseGuard();

        config([
            'app.env' => 'production',
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'vani_setu_prod',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked DatabaseSeeder against protected production database');

        $guard->assertSeedingAllowed('DatabaseSeeder');
    }
}
