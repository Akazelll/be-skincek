<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'assign roles',
            'review doctor verifications',
            'manage catalog',
            'view activity log',
            'submit doctor verification',
            'manage products',
            'manage recommendations',
            'reply conversations',
            'scan skin',
            'create conversations',
            'send messages',
            'checkout subscription',
            'register device token',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $doctor = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $admin->syncPermissions($permissions);
        $doctor->syncPermissions([
            'submit doctor verification',
            'manage products',
            'manage recommendations',
            'reply conversations',
        ]);
        $user->syncPermissions([
            'scan skin',
            'create conversations',
            'send messages',
            'checkout subscription',
            'register device token',
        ]);
    }
}
