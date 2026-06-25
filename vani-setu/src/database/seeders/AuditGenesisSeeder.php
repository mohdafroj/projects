<?php

namespace Database\Seeders;

use App\Modules\Core\Services\Audit\AuditLogger;
use Illuminate\Database\Seeder;

class AuditGenesisSeeder extends Seeder
{
    /**
     * Seed the genesis audit row once.
     */
    public function run(): void
    {
        if (app('db')->table('audit_logs')->where('action', 'system.audit.init')->exists()) {
            return;
        }

        app()->instance('audit.actor_id', null);
        app()->instance('audit.actor_role', 'system');

        app(AuditLogger::class)->log('system.audit.init', null, [
            'version' => '1',
            'scope' => 'vani-setu',
            'note' => 'genesis',
        ]);
    }
}
