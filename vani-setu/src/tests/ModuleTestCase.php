<?php

namespace Tests;

use App\Modules\Capture\Seeders\DemoAssignmentSeeder;
use App\Modules\Capture\Seeders\DemoBlockSeeder;
use App\Modules\Capture\Seeders\DemoReporterSeeder;
use App\Modules\Capture\Seeders\DemoSittingSeeder;
use App\Modules\Capture\Seeders\DemoSupervisorSeeder;
use Database\Seeders\AuditGenesisSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class ModuleTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seedModuleBase(): void
    {
        $this->seed([
            AuditGenesisSeeder::class,
            RolePermissionSeeder::class,
            DemoSittingSeeder::class,
            DemoBlockSeeder::class,
            DemoReporterSeeder::class,
            DemoAssignmentSeeder::class,
            DemoSupervisorSeeder::class,
        ]);
    }
}
