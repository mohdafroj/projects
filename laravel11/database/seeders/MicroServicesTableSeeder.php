<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MicroServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $table = 'micro_services';
    public function run(): void
    {
        DB::table($this->table)->insert([
            'code' => 'noticont',
            'title' => 'Notification Controller',
            'description' => 'It is a notification controller micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'rbac',
            'title' => 'Role Based Access Controll (RBAC)',
            'description' => 'It is a role based access controll micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'budget',
            'title' => 'Financial Budget',
            'description' => 'It is a financial budget micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'attendance',
            'title' => 'Attendance Management',
            'description' => 'It is an attendance management system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'membserv',
            'title' => 'Member Services',
            'description' => 'It is a member services micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'comm',
            'title' => 'Committee',
            'description' => 'It is a committee micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'rsssecr',
            'title' => 'RSS Secretariat',
            'description' => 'It is a RSS Secretariat micro service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
