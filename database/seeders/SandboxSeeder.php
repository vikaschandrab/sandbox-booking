<?php

namespace Database\Seeders;

use App\Models\Sandbox;
use Illuminate\Database\Seeder;

/**
 * Seeds the example sandbox inventory.
 */
class SandboxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'win10-sandbox-1', 'os_family' => 'windows'],
            ['name' => 'win11-sandbox-1', 'os_family' => 'windows'],
            ['name' => 'ubuntu-sandbox-1', 'os_family' => 'linux'],
            ['name' => 'ubuntu-sandbox-2', 'os_family' => 'linux'],
            ['name' => 'win10-sandbox-2', 'os_family' => 'windows'],
        ] as $sandbox) {
            Sandbox::updateOrCreate(
                ['name' => $sandbox['name']],
                ['os_family' => $sandbox['os_family']],
            );
        }
    }
}
