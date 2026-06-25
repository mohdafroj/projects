<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the Vani Setu IAM roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'capture.record',
            'capture.edit',
            'capture.commit',
            'editorial.view',
            'editorial.edit',
            'editorial.consolidate',
            'editorial.expunge.suggest',
            'workflow.return',
            'workflow.forward.supervisor',
            'workflow.forward.chief',
            'workflow.forward.js',
            'workflow.forward.sg',
            'workflow.forward.director',
            'decision.expunge.confirm',
            'decision.dsc.sign',
            'publish.crc',
            'publish.digital.sansad',
            'admin.users.manage',
            'admin.roles.manage',
            'admin.system',
            'translator.queue',
            'translator.edit',
            'translator.commit',
            'translator.glossary.manage',
            's2s.placeholder',
            'committee.sitting.manage',
            'committee.capture.commit',
            'committee.workflow.review',
            'committee.chair.sign',
            'committee.report.lay',
            'committee.in_camera.view',
            'committee.in_camera.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'reporter' => [
                'display_name' => 'Reporter / SSA-SA',
                'permissions' => [
                    'capture.record',
                    'capture.edit',
                    'capture.commit',
                ],
            ],
            'supervisor' => [
                'display_name' => 'Supervisor',
                'permissions' => [
                    'capture.record',
                    'capture.edit',
                    'capture.commit',
                    'workflow.return',
                    'workflow.forward.chief',
                ],
            ],
            'chief' => [
                'display_name' => 'Chief / Director',
                'permissions' => [
                    'editorial.view',
                    'editorial.edit',
                    'editorial.consolidate',
                    'editorial.expunge.suggest',
                    'workflow.return',
                    'workflow.forward.js',
                    'capture.edit',
                ],
            ],
            'js' => [
                'display_name' => 'Joint Secretary',
                'permissions' => [
                    'editorial.view',
                    'workflow.return',
                    'workflow.forward.sg',
                    'workflow.forward.director',
                    'decision.expunge.confirm',
                ],
            ],
            'sg' => [
                'display_name' => 'Secretary General',
                'permissions' => [
                    'decision.expunge.confirm',
                    'decision.dsc.sign',
                ],
            ],
            'translator' => [
                'display_name' => 'Translator / E&T',
                'permissions' => [
                    'translator.queue',
                    'translator.edit',
                    'translator.commit',
                    'translator.glossary.manage',
                ],
            ],
            'admin' => [
                'display_name' => 'Systems Admin',
                'permissions' => $permissions,
            ],
            'committee_chair' => [
                'display_name' => 'Committee Chair',
                'permissions' => [
                    'committee.workflow.review',
                    'committee.chair.sign',
                    'committee.in_camera.view',
                ],
            ],
            'committee_secretary' => [
                'display_name' => 'Committee Secretary',
                'permissions' => [
                    'committee.sitting.manage',
                    'committee.capture.commit',
                    'committee.workflow.review',
                    'committee.report.lay',
                    'committee.in_camera.view',
                    'committee.in_camera.manage',
                ],
            ],
            'committee_secretariat' => [
                'display_name' => 'Committee Secretariat Staff',
                'permissions' => [
                    'committee.sitting.manage',
                    'committee.capture.commit',
                    'committee.workflow.review',
                    'committee.report.lay',
                    'committee.in_camera.view',
                    'committee.in_camera.manage',
                ],
            ],
            'committee_witness' => [
                'display_name' => 'Committee Witness',
                'permissions' => [],
            ],
            'committee_observer' => [
                'display_name' => 'Committee Observer',
                'permissions' => [],
            ],
        ];

        foreach ($roles as $name => $definition) {
            $role = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $role->forceFill([
                'display_name' => $definition['display_name'],
            ])->save();

            $role->syncPermissions($definition['permissions']);
        }

        $this->seedBootstrapAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedBootstrapAdmin(): void
    {
        if (! env('BOOTSTRAP_ADMIN_PASSWORD') && ! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping bootstrap admin outside local/testing without BOOTSTRAP_ADMIN_PASSWORD.');
            return;
        }

        $email = env('BOOTSTRAP_ADMIN_EMAIL') ?: 'adm-001@vanisetu.local';
        $password = env('BOOTSTRAP_ADMIN_PASSWORD') ?: Str::password(24);
        $employeeId = env('BOOTSTRAP_ADMIN_EMPLOYEE_ID') ?: 'ADM-001';

        $admin = User::updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'name' => 'Bootstrap Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'language_competencies' => [],
            ],
        );

        $admin->assignRole('admin');
    }
}
