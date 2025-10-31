<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $table = 'permissions';
    public function run(): void
    {
        DB::table($this->table)->insert([
            'code' => 'create',
            'title' => 'Create',
            'description' => 'It is used to create permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'view',
            'title' => 'View',
            'description' => 'It is used to view permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'update',
            'title' => 'Update',
            'description' => 'It is used to update permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'delete',
            'title' => 'Delete',
            'description' => 'It is used to delete permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'initiate',
            'title' => 'Initiate',
            'description' => 'It is used to initiate permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'review',
            'title' => 'Review',
            'description' => 'It is used to review permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table($this->table)->insert([
            'code' => 'approve',
            'title' => 'Approve',
            'description' => 'It is used to approve permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
