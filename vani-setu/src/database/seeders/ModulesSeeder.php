<?php

namespace Database\Seeders;

use App\Providers\ModuleServiceProvider;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModuleServiceProvider::seeders() as $seeders) {
            foreach ($seeders as $seeder) {
                $this->call($seeder);
            }
        }
    }
}
