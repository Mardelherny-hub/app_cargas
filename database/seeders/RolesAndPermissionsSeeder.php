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

        // Create permissions - mantener todos para compatibilidad futura
        $permissions = [
            // Users management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Companies management
            'companies.view',
            'companies.create',
            'companies.edit',
            'companies.delete',
            'companies.certificates',

            // Trips management
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',
            'trips.transfer',

            // Shipments/Loads management
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.own_only', // Only from own company

            // Containers management
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',

            // Transshipments management (CARGAS role)
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',

            // Deconsolidations management (DESCONSOLIDADOR role)
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',

            // Attachments
            'attachments.view',
            'attachments.upload',
            'attachments.delete',

            // Import/Export capabilities
            'import.excel',
            'import.xml',
            'import.edi',
            'import.cuscar',
            'import.txt',
            'export.data',
            'export.transfer_company', // Roberto's requirement

            // Webservices by company role
            'webservices.anticipada',   // CARGAS
            'webservices.micdta',       // CARGAS
            'webservices.desconsolidados', // DESCONSOLIDADOR
            'webservices.transbordos',  // TRANSBORDOS
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

        // SIMPLIFIED ROLES according to Roberto's feedback - SOLO 3 ROLES

        // 1. Super Admin - Only creates companies and assigns company roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->givePermissionTo([
            'companies.view',
            'companies.create',
            'companies.edit',
            'companies.delete',
            'companies.certificates',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'admin.configuration',
            'admin.audit',
            'admin.backups',
            'admin.reference_tables',
        ]);

        // 2. Company Admin - Manages users within their company (Roberto's "jefe")
        $companyAdmin = Role::firstOrCreate(['name' => 'company-admin']);
        $companyAdmin->givePermissionTo([
            // User management within their company
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // All company operations (will be filtered by company roles)
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',
            'trips.transfer',
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.own_only',
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',
            'attachments.view',
            'attachments.upload',
            'attachments.delete',
            'import.excel',
            'import.xml',
            'import.edi',
            'import.cuscar',
            'import.txt',
            'export.data',
            'export.transfer_company',
            'webservices.anticipada',
            'webservices.micdta',
            'webservices.desconsolidados',
            'webservices.transbordos',
            'webservices.send',
            'webservices.query',
            'webservices.rectify',
            'webservices.cancel',
            'reports.manifests',
            'reports.bills_of_lading',
            'reports.micdta',
            'reports.arrival_notices',
            'reports.customs',
        ]);

        // 3. User - Can do EVERYTHING their company roles allow (Roberto's concept)
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->givePermissionTo([
            // All operational permissions (will be filtered by company roles)
            'trips.view',
            'trips.create',
            'trips.edit',
            'trips.delete',
            'trips.close',
            'trips.transfer',
            'shipments.view',
            'shipments.create',
            'shipments.edit',
            'shipments.delete',
            'shipments.own_only',
            'containers.view',
            'containers.create',
            'containers.edit',
            'containers.delete',
            'transshipments.view',
            'transshipments.create',
            'transshipments.edit',
            'transshipments.delete',
            'deconsolidations.view',
            'deconsolidations.create',
            'deconsolidations.edit',
            'deconsolidations.delete',
            'attachments.view',
            'attachments.upload',
            'attachments.delete',
            'import.excel',
            'import.xml',
            'import.edi',
            'import.cuscar',
            'import.txt',
            'export.data',
            'export.transfer_company',
            'webservices.anticipada',
            'webservices.micdta',
            'webservices.desconsolidados',
            'webservices.transbordos',
            'webservices.send',
            'webservices.query',
            'webservices.rectify',
            'webservices.cancel',
            'reports.manifests',
            'reports.bills_of_lading',
            'reports.micdta',
            'reports.arrival_notices',
            'reports.customs',
        ]);

        $this->command->info('✅ Simplified roles and permissions created successfully');
        $this->command->info('');
        $this->command->info('=== 👥 ROLES CREATED (Roberto\'s structure) ===');
        $this->command->info('• super-admin: Creates companies & assigns company roles');
        $this->command->info('• company-admin: Manages users within company (the "jefe")');
        $this->command->info('• user: Can do everything their company roles allow');
        $this->command->info('');
        $this->command->info('=== 🏢 COMPANY ROLES (actual business roles) ===');
        $this->command->info('• "Cargas": webservices anticipada + micdta');
        $this->command->info('• "Desconsolidador": webservice desconsolidados');
        $this->command->info('• "Transbordos": webservice transbordos');
        $this->command->info('• Companies can have multiple roles: ["Cargas", "Transbordos"]');
        $this->command->info('');
        $this->command->info('=== 🔑 IMPORTANT NOTES ===');
        $this->command->info('• User permissions are now FILTERED by company roles');
        $this->command->info('• Each company sees only THEIR information');
        $this->command->info('• Export/import between companies available');
        $this->command->info('• Users can change their own password');
        $this->command->info('');
        $this->command->info('Total permissions: ' . count($permissions));
    }
}
