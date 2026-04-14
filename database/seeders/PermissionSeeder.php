<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'invoice.view',
            'invoice.create',
            'invoice.update',
            'invoice.delete',
            'purchasing.view',
            'purchasing.create',
            'purchasing.update',
            'purchasing.delete',
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'fixed-asset.view',
            'fixed-asset.create',
            'fixed-asset.update',
            'fixed-asset.delete',
            'report.financial.view',
            'report.business.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $owner = Role::findByName('Owner', 'web');
        $owner->syncPermissions($permissions);

        $admin = Role::findByName('Admin', 'web');
        $admin->syncPermissions($permissions);

        $akuntan = Role::findByName('Akuntan', 'web');
        $akuntan->syncPermissions([
            'invoice.view',
            'invoice.create',
            'invoice.update',
            'purchasing.view',
            'purchasing.create',
            'purchasing.update',
            'inventory.view',
            'inventory.update',
            'fixed-asset.view',
            'fixed-asset.create',
            'fixed-asset.update',
            'report.financial.view',
            'report.business.view',
        ]);

        $kasir = Role::findByName('Kasir', 'web');
        $kasir->syncPermissions([
            'invoice.view',
            'invoice.create',
            'purchasing.view',
            'inventory.view',
            'inventory.update',
            'report.business.view',
        ]);

        $viewer = Role::findByName('Viewer', 'web');
        $viewer->syncPermissions([
            'invoice.view',
            'purchasing.view',
            'inventory.view',
            'fixed-asset.view',
            'report.financial.view',
            'report.business.view',
        ]);
    }
}
