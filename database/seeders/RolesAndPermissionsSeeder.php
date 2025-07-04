<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Companies
            'companies.view',
            'companies.create',
            'companies.edit',
            'companies.delete',
            'companies.certificates',

            // Trips
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',
            'trips.transfer',

            // Shipments/Loads
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.own_only', // Only shipments from own company

            // Containers
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',

            // Transshipments
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',

            // Deconsolidations
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',

            // Attachments
            'attachments.view',
            'attachments.upload',
            'attachments.delete',

            // Import/Export
            'import.excel',
            'import.xml',
            'import.edi',
            'import.cuscar',
            'import.txt',
            'export.data',

            // Webservices
            'webservices.send',
            'webservices.query',
            'webservices.rectify',
            'webservices.cancel',

            // Reports
            'reports.manifests',
            'reports.bills_of_lading',
            'reports.micdta',
            'reports.arrival_notices',
            'reports.customs',

            // Administration
            'admin.configuration',
            'admin.audit',
            'admin.backups',
            'admin.reference_tables',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles

        // 1. Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Company Admin
        $companyAdmin = Role::firstOrCreate(['name' => 'company-admin']);
        $companyAdmin->givePermissionTo([
            // Users from their company
            'users.view',
            'users.create',
            'users.edit',

            // Their company
            'companies.view',
            'companies.edit',
            'companies.certificates',

            // Trips from their company
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',
            'trips.transfer',

            // Shipments from their company
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.own_only',

            // Containers
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',

            // Transshipments and deconsolidations
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',

            // Attachments
            'attachments.view',
            'attachments.upload',
            'attachments.delete',

            // Import/Export
            'import.excel',
            'import.xml',
            'import.edi',
            'import.cuscar',
            'import.txt',
            'export.data',

            // Webservices
            'webservices.send',
            'webservices.query',
            'webservices.rectify',
            'webservices.cancel',

            // Reports
            'reports.manifests',
            'reports.bills_of_lading',
            'reports.micdta',
            'reports.arrival_notices',
            'reports.customs',
        ]);

        // 3. Internal Operator
        $internalOperator = Role::firstOrCreate(['name' => 'internal-operator']);
        $internalOperator->givePermissionTo([
            // Trips
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',

            // Shipments
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',

            // Containers
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',

            // Transshipments and deconsolidations
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',

            // Attachments
            'attachments.view',
            'attachments.upload',

            // Basic import
            'import.excel',
            'import.xml',

            // Webservices
            'webservices.send',
            'webservices.query',

            // Reports
            'reports.manifests',
            'reports.bills_of_lading',
            'reports.micdta',
        ]);

        // 4. External Operator
        $externalOperator = Role::firstOrCreate(['name' => 'external-operator']);
        $externalOperator->givePermissionTo([
            // Only trips from their company
            'trips.view',
            'trips.create',
            'trips.edit',

            // Only shipments from their company
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.own_only',

            // Containers
            'containers.view',
            'containers.create',
            'containers.edit',

            // Transshipments and deconsolidations
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',

            // Attachments
            'attachments.view',
            'attachments.upload',

            // Basic import
            'import.excel',
            'import.xml',

            // Basic reports
            'reports.manifests',
            'reports.bills_of_lading',
        ]);

        $this->command->info('Roles and permissions created successfully');
        $this->command->info('Created roles: super-admin, company-admin, internal-operator, external-operator');
        $this->command->info('Created ' . count($permissions) . ' permissions');
    }
}
