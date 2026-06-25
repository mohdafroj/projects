<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoReporterSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping demo reporters outside local/testing.');
            return;
        }

        foreach ($this->reporters() as $index => [$name, $langs]) {
            $user = User::query()->updateOrCreate(
                ['employee_id' => sprintf('RPT-%03d', $index + 1)],
                [
                    'name' => $name,
                    'email' => sprintf('rpt-%03d@vanisetu.local', $index + 1),
                    'password' => Hash::make((string) (env('DEMO_USER_PASSWORD') ?: Str::password(24))),
                    'language_competencies' => $langs,
                    'is_active' => true,
                ],
            );

            $user->assignRole('reporter');
        }
    }

    private function reporters(): array
    {
        return [
            ['A. Sharma', ['en']],
            ['K. Iyer', ['hi']],
            ['S. Venkatesan', ['ta', 'en']],
            ['M. Ahmed', ['ur', 'en']],
            ['R. Chakraborty', ['bn', 'en']],
            ['B. Mehta', ['en']],
            ['L. Joshi', ['hi']],
            ['F. Khan', ['ur', 'hi']],
            ['C. Pillai', ['en']],
            ['P. Verma', ['hi']],
            ['D. Rao', ['en']],
            ['N. Singh', ['hi']],
            ['E. Naidu', ['en']],
            ['T. Trivedi', ['hi']],
            ['V. Bhonsle', ['mr', 'en']],
            ['G. Kapoor', ['en']],
            ['V. Pandey', ['hi']],
            ['X. Menon', ['en', 'hi']],
        ];
    }
}
