<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->firstOrCreate(['name' => 'admin']);
        Role::query()->firstOrCreate(['name' => 'jagal']);
        Role::query()->firstOrCreate(['name' => 'kuliti']);
        Role::query()->firstOrCreate(['name' => 'cacah_daging']);
        Role::query()->firstOrCreate(['name' => 'cacah_tulang']);
        Role::query()->firstOrCreate(['name' => 'jeroan']);
        Role::query()->firstOrCreate(['name' => 'packing']);
        Role::query()->firstOrCreate(['name' => 'distribusi']);
        Role::query()->firstOrCreate(['name' => 'penimbang']);
    }
}
